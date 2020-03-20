<?php namespace ProcessWire;

$tmpTemplates = wire('templates');
$auctionTemplates = array();
foreach($tmpTemplates as $template) { // exclude system templates
  if($template->flags & Template::flagSystem) continue;
  $auctionTemplates[$template->id] = $template;
}

$config = array(
  'auctionLength' => array(
    'type' => 'integer',
    'label' => __('Length of the auction'),
    'description' => __('Length of the auction in seconds.'), 
    'value' => '1209600',
    'required' => true,
    'notes' => __('The lenght of the Auction, and the size of the steps defines the price difference per step. Defaults to 1209600 (14 days)')
  ),
  'stepSize' => array(
    'type' => 'integer',
    'label' => __('Step size'),
    'description' => __('Calculate the price every n seconds'),
    'value' => '1',
    'required' => true, 
    'notes' => __('The lenght of the Auction, and the size of the steps defines the price difference per step. Defaults to 1 second')
  ),
  'auctionTemplates' => array(
    'type' => 'InputfieldAsmSelect',
    'options' => $auctionTemplates,
    'label' => __('Auction templates'),
    'description' => __('Select the templates for the auctions'),
    'value' => '1',
    'required' => true
  )
);