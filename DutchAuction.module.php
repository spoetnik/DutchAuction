<?php namespace ProcessWire;

/**
 * ProcessWire 'Hello world' demonstration module
 *
 * Demonstrates the Module interface and how to add hooks.
 * 
 * See README file for further links regarding module development.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class DutchAuction extends WireData implements Module {

	/**
	 * Initialize the module
	 *
	 * ProcessWire calls this when the module is loaded. For 'autoload' modules, this will be called
	 * when ProcessWire's API is ready. As a result, this is a good place to attach hooks. 
	 *
	 */
	public function init() {
		// Default auction length to two weeks
		if(!$this->auctionLength) $this->auctionLength = 1209600;
		// default step size
		if(!$this->stepSize) $this->stepSize = 1;
		// default template if non is set
		if(!$this->auctionTemplates) $this->auctionTemplates = 'auction';

		// Add lazycron hook to update the currentprices
		$this->addHook('LazyCron::every5Minutes', $this, 'updatePrices');
		// Add lazycron hook to archive expired auctions
		$this->addHook('LazyCron::every5Minutes', $this, 'archiveAuctions');
	}

	public function ready () {
	}

	/**
	 * Hoeveel Steps zijn er verlopen sinds tijdpublish
	 * 
	 * @param Date $dtstart
	 * @param stepSize $stepsize
	 */
	protected function stepsPassed($dtstart) {
		$dtstart = new \DateTime( $dtstart );
		$now = new \DateTime();
		$stepSize = $this->stepSize;

		$interval = $now->getTimestamp() - $dtstart->getTimestamp();
		$stepsPassed = $interval/$stepSize;

		return $stepsPassed;
	}


	/**
	 *  Wat 'Kost' 1 Step
	 * 
	 * @param maxPrice $maxPrice
	 * @param minPrice $minPrice
	 */
	protected function stepPrice($maxPrice, $minPrice) {
		if(isset($maxPrice, $minPrice) && $maxPrice > $minPrice){
			$numSteps = $this->auctionLength/$this->stepSize;
			$stepPrice = ($maxPrice-$minPrice)/$numSteps;
		} else {
			$stepPrice = NULL;
		}
			return $stepPrice;
	}


	/**
	 *  Wat is de huidige prijs
	 * 		$currentprice = $maxprice-($stepsPassed*$stepPrice)
	 * 
	 * @param date_published $date_published
	 * @param maxPrice $maxPrice
	 * @param minPrice $minPrice
	 */	
	public function currentPrice($date_published, $maxPrice, $minPrice) {
		if(!isset($date_published, $maxPrice, $minPrice)) {
			return NULL;
		}
		$stepsPassed = $this->stepsPassed($date_published);
		$stepPrice = $this->stepPrice($maxPrice, $minPrice);

		if($stepPrice) {
			$currentPrice = $maxPrice-($stepsPassed*$stepPrice);
		} else {
			$currentPrice = $maxPrice;
		}
		// Never return a negative price
		if ($currentPrice >= $minPrice){
			return $currentPrice;
		}else{
			return $minPrice;
		}
		
	}

	// updates the prices by cron every 5 minutes
	protected function updatePrices(HookEvent $e) {
		$seconds = $e->arguments[0];
		$startTime = microtime(true);
		wire('log')->save('dutchauction', 'updatePrices triggered by cron (' . $seconds .' seconds  sinds last run...)');
		$auctioncouter = 0;
		foreach($this->auctionTemplates as $auctiontemplate):
			foreach(pages()->find("template=$auctiontemplate") as $auction):
				$date_published = gmdate('Y-m-d\TH:i:s', $auction->published);
				$currentPrice = $this->currentPrice($date_published, $auction->maxprice, $auction->minprice);
				$auction->currentprice = $currentPrice;
				$auction->of(false);
				$auction->save();
				$auction->of(true);
				$auctioncouter ++;
			endforeach;
		endforeach;
		wire('log')->save('dutchauction', 'Updated ' . $auctioncouter .' auction prices in '. (microtime(true) - $startTime) . ' seconds');
	}

	// Archive auctions older than auctionLength
	protected function archiveAuctions(HookEvent $e) {
		$seconds = $e->arguments[0];
		$startTime = microtime(true);
		wire('log')->save('dutchauction', 'archiveAuctions triggered by cron (' . $seconds .' seconds  sinds last run...)');
		$auctioncouter = 0;

		$now = new \DateTime();
		$dtArchive = $now->getTimestamp() - $this->auctionLength;

		$archiveParent = '/archive/';
		$archiveParentYear = $now->format('Y');
		$archiveParentMonth = $now->format('m');
		$archiveParentDay = $now->format('d');

		// Create the archive structure. Nested y->M->D
		$archiveParentNow = pages()->get($archiveParent);
		if (!$archiveParentNow->id ) {
			$archiveParentNow = wire(new Page());
			$archiveParentNow->template = 'auctionoverview';
			$archiveParentNow->parent = wire('pages')->get('/');
			$archiveParentNow->title = 'Archive';
			$archiveParentNow->save();
			wire('log')->save('dutchauction', 'Created ' . $archiveParentNow .' page');
		}

		$archiveParentYearNow = pages()->get("parent=$archiveParent, title=$archiveParentYear");
		if (!$archiveParentYearNow->id ) {
			$archiveParentYearNow = wire(new Page());
			$archiveParentYearNow->template = 'auctionoverview';
			$archiveParentYearNow->parent = $archiveParentNow;
			$archiveParentYearNow->title = $archiveParentYear;
			$archiveParentYearNow->save();
			wire('log')->save('dutchauction', 'Created ' . $archiveParentYearNow .' page');
		}

		$archiveParentMonthNow = pages()->get("parent=$archiveParentYearNow, title=$archiveParentMonth");
		if (!$archiveParentMonthNow->id ) {
			$archiveParentMonthNow = wire(new Page());
			$archiveParentMonthNow->template = 'auctionoverview';
			$archiveParentMonthNow->parent = $archiveParentYearNow;
			$archiveParentMonthNow->title = $archiveParentMonth;
			$archiveParentMonthNow->save();
			wire('log')->save('dutchauction', 'Created ' . $archiveParentMonthNow .' page');
		}
	
		$archiveParentDayNow = pages()->get("parent=$archiveParentMonthNow, title=$archiveParentDay");
		if (!$archiveParentDayNow->id ) {
			$archiveParentDayNow = wire(new Page());
			$archiveParentDayNow->template = 'auctionoverview';
			$archiveParentDayNow->parent = $archiveParentMonthNow;
			$archiveParentDayNow->title = $archiveParentDay;
			$archiveParentDayNow->save();
			wire('log')->save('dutchauction', 'Created ' . $archiveParentDayNow .' page');
		}
		
		foreach(pages()->find("template=$auctiontemplate") as $auction):
			if($auction->published < $dtArchive){
				$auction->parent = $archiveParentDayNow;
				$auction->addStatus(Page::statusUnpublished);;
				$auction->of(false);
				$auction->save();
				$auction->of(true);
				$auctioncouter ++;
			}
		endforeach;
		wire('log')->save('dutchauction', 'Archived ' . $auctioncouter .' auctions in '. (microtime(true) - $startTime) . ' seconds');
	}


}
