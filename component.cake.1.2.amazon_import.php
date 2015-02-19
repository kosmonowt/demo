<?php
/**
 * Diese Klasse erstellt Bestellungen, die per Datei in ein Warenwirtschaftssystem importiert werden.
 * Entirely created by Andreas Kosmowicz. All new code.
 * 
 * Felder aus Amazon-Order benötigt:
 * MODEL "ORDER":
 * * order-id				-> amazon_ba_no
 * * recipient-name			-> shipping_address - 1st row
 * * ship-address-1			-> shipping_address - 2nd row
 * * ship-address-2			-> shipping_address - 2nd row "b"
 * * ship-address-3			-> shipping_address - 2nd row "c"
 * * ship-postal-code		-> shipping_address - 3rd row
 * * ship-city				-> shipping_address - 3rd row
 * * ship-country			-> shipping_address - 4th row (if different from DE)
 * * ship-phone-number		-> phone
 * * buyer-name				-> invoice_address - 1st row
 * * bill-address-1			-> invoice_address - 2nd row
 * * bill-address-2			-> invoice_address - 2nd row "b"
 * * bill-address-3			-> invoice_address - 2nd row "c"
 * * bill-city				-> invoice_address - 3rd row
 * * bill-postal-code		-> invoice_address - 3rd row
 * * bill-country			-> invoice_address - 4th row
 * * sales-channel			-> SET TO enterprise_id (that matches amazon.de or amazon.eu)
 * 
 * MODEL "ORDER_PRODUCT"
 * * sku					-> ASSIGN TO product (!IMPORTANT! when not exactly the same write message into order_state table)
 * * item-price				-> price
 * * quantity-purchased		-> amount
 *
 *
 * MODEL "ORDER_STATE" 
 * * currency 				-> if different from EUR WRITE MESSAGE
 *   
 * @author kosmo
 *
 */
class AmazonSyncComponent extends Object {
	
	private $Controller;
	
	private $importData;
	private $enterprise_id;
	private $AmazonProduct = null;
	private $ShippingProduct = null;
	 
	private $currentOrderProduct;
	
	/**
	 * Array: [amazon_ba_nr] => [order_id]
	 * @var array
	 */
	private $createdOrders = array();
	
	private $ordersInDb = array();
	
	private $errors = array();
	
	public function startup($controller) {
		$this->Controller = $controller;	
	}
		
	/**
	 * parses the imported amazon-export-file to an array. 
	 * @param string $filename
	 * @return array - the data
	 */
	protected function parseImportfile($filename) {
		$r = fopen($filename,"r");
		
		$header = explode("\t",utf8_encode(fgets($r)));

		// It's a tabbed String file so in each row we find values divided by tab (\t)
		while ($row = fgets($r)) {
			$row = utf8_encode($row);
			foreach (explode("\t",$row) as $i => $c) {
				$dataRow[trim($header[$i])] = $c;
			}
			$data[] = $dataRow;
		}
		
		fclose($r);
		
		return $data;
	}
	
	/**
	 * This sets the order state to "created"
	 */
	protected function addOrderState($order_id, $state) {
		$this->Controller->Order->OrderState->create();
		return $this->Controller->Order->OrderState->save(array(
				"OrderState"=>array(
						"order_id" => $order_id,
						"title" => $state
				)));
	}
	
	protected function addOrderStateCreated($order_id) {
		return $this->addOrderState($order_id,"order_created");
	}
	
	protected function addOrderStatePaid($order_id) {
		return $this->addOrderState($order_id,"customer_payed");
	}
	
	/**
	 * This sets an user action in case some special things happen:
	 * i.E. not possible to assign sku to product
	 * i.E. different currency
	 * i.E. different country
	 * @var int $order_id OrderId
	 * @var string $message Message
	 * @var boo $important Set to true if sets message as User-Generated (Ausrufezeichen in Übersicht)
	 */
	protected function addUserAction($order_id, $message, $important = false) {
		$this->Controller->Order->UserAction->create();
		return $this->Controller->Order->UserAction->save(array(
				"UserAction" => array(
						"order_id" => $order_id,
						"title" => $message,
						"is_system_generated" => intval(!$important)
			)));
	}
	
	protected function getEnterpriseIdForSalesChannel() {
		// TODO: once change the enterprise_id
		return $this->enterprise_id;
	}
	
	/**
	 * Creates the order
	* * order-id				-> amazon_ba_no
	* * recipient-name			-> shipping_address - 1st row
	* * ship-address-1			-> shipping_address - 2nd row
	* * ship-address-2			-> shipping_address - 2nd row "b"
	* * ship-address-3			-> shipping_address - 2nd row "c"
	* * ship-postal-code		-> shipping_address - 3rd row
	* * ship-city				-> shipping_address - 3rd row
	* * ship-country			-> shipping_address - 4th row (if different from DE)
	* * ship-phone-number		-> phone
	* * buyer-name				-> invoice_address - 1st row
	* * bill-address-1			-> invoice_address - 2nd row
	* * bill-address-2			-> invoice_address - 2nd row "b"
	* * bill-address-3			-> invoice_address - 2nd row "c"
	* * bill-city				-> invoice_address - 3rd row
	* * bill-postal-code		-> invoice_address - 3rd row
	* * bill-country			-> invoice_address - 4th row
	* * sales-channel			-> SET TO enterprise_id (that matches amazon.de or amazon.eu)		
	 */
	protected function createOrder($row) {

		$order['ba_nr'] = $this->Controller->Order->getNextBaState();
		$order['amazon_ba_no'] = $row["order-id"];
		$order['phone'] = $row['ship-phone-number'];
		$order['created'] = date("Y-m-d H:i:s", strtotime($row['purchase-date']));
		$order['shipping_address'][] = $row['recipient-name'];
		$order['shipping_address'][] = $row['ship-address-1'];
		if (strlen($row['ship-address-2'])) $order['shipping_address'][] .= $row['ship-address-2'];
		if (strlen($row['ship-address-3'])) $order['shipping_address'][] .= $row['ship-address-3'];
		$order['shipping_address'][] = $row['ship-postal-code']." ".$row['ship-city'];
		if ($row['ship-country'] != "DE") {
			$order['shipping_address'][] = $row['ship-country'];
			// WRITE MESSAGE TO LOG LATER
		}
		$shippingAddress = implode("\n",$order['shipping_address']);

		
		
		$order['invoice_address'][] = $row['recipient-name'];
		$order['invoice_address'][] = $row['ship-address-1'];
		if (strlen($row['ship-address-2'])) $order['invoice_address'][] .= $row['ship-address-2'];
		if (strlen($row['ship-address-3'])) $order['invoice_address'][] .= $row['ship-address-3'];
		$order['invoice_address'][] = $row['ship-postal-code']." ".$row['ship-city'];
		if ($row['ship-country'] != "DE") {
			$order['invoice_address'][] = $row['ship-country'];
			// WRITE MESSAGE TO LOG LATER
		}
		$invoiceAddress = implode("\n",$order['invoice_address']);

		if ($invoiceAddress != $shippingAddress) {
			//Only add shipping address if different to shipping address
			$order['invoice_address'] = $invoiceAddress;
			$order['shipping_address'] = $shippingAddress;
		} else {
			// Normally we only keep invoice Address
			unset($order['shipping_address']);
			$order['invoice_address'] = $invoiceAddress; 
		}
		
		$order['enterprise_id'] = $this->getEnterpriseIdForSalesChannel($row['sales-channel']);
		
		$this->Controller->Order->create();
		if ($this->Controller->Order->save(array("Order" => $order))) {
			
			$order_id = $this->Controller->Order->getLastInsertId();
			
			if (!$this->addOrderStateCreated($order_id)) {
				//return false;
				$this->errors[] = array(
					"row" => $row,
					"message" => "Error Creating Order State 'Created' for Order",
					"order_id" => $order_id	
				);
			}
			
			if (!$this->addOrderStatePaid($order_id)) {
				//return false;
				$this->errors[] = array(
						"row" => $row,
						"message" => "Error Creating Order State 'Paid' for Order",
						"order_id" => $order_id
				);
			}
			
			if (!$this->addShippingToOrder($order_id)) {
				// ADD ERROR MESSAGE
				$this->errors[] = array(
						"row" => $row,
						"message" => "Error Creating Shipping OrderProduct for Order",
						"order_id" => $order_id
				);
			}
			
			$this->addUserAction($order_id,"Auftrag erstellt duch AmazonImport. (".$row['sales-channel'].")");

			// CHECK FOR FOREIGN CURRENCY
			if (strtoupper($row['currency']) !== "EUR") $this->addUserAction($order_id,"Achtung: Preis in ".$row['currency']." ausgezeichnet! Möglicherweise Verkaufskanal ändern!",true); 
			
			
			// Save OrderId -> Amazon Order ID Relation
			$this->createdOrders[$row['order-id']]['order_id'] = $order_id;
			$this->createdOrders[$row['order-id']]['amazon_ba_no'] = $row['order-id'];
			$this->createdOrders[$row['order-id']]['items_count'] = 0;
			$this->createdOrders[$row['order-id']]['invocations'] = 1;
			
			return $order_id;
			
		} else {

			$this->errors[] = array(
					"row" => $row,
					"message" => "Error Creating Order!",
					"Order" => $order
			);
			
			return false;
		}
		
		
	}
	
	protected function findItemNumberFromAmazonItem($row) {
		
		if (is_null($this->AmazonProduct)) {
			App::Import('Model','AmazonProduct');
			$this->AmazonProduct = new AmazonProduct();
		}
		$amazonProduct = $this->AmazonProduct->find("first",array(
			"conditions" => array(
					"sku LIKE" => $row['sku']
			)
		));
	
		if ($amazonProduct) {
			
			return $amazonProduct['AmazonProduct']['product_id'];
			
		} else {
			// Create new AmazonProduct if none found
			$apData = array(
				"AmazonProduct" => 
				array(
					"sku" => $row['sku'],
					"product_id" => 0,
					"description" => $row["product-name"]
				)
			);
			$this->AmazonProduct->create();	
			
			if (!$this->AmazonProduct->save($apData)) {
				$this->errors[] = array(
						"row" => $row,
						"message" => "Error Creating Amazon Product for Order",
						"apData" => $apData
				);
			}
			
			return 0;
		}
		
	}
	
	/**
	 * Adds shipping to created Order
	 * @param int $order_id
	 * @return boolean true
	 */
	protected function addShippingToOrder($order_id) {
		if (is_null($this->ShippingProduct)) {
			App::Import("Model","Product");
			$P = new Product();
			// If you want to define a different criteria just change this data in bootstrap.php
			$shippingProductIdField = Configure::read("shippingProductIdField");
			if (is_null($shippingProductIdField)) $shippingProductIdField = "alt_item_number";
			$shippingProductId = Configure::read("shippingProductId");
			if (is_null($shippingProductId)) $shippingProductId = "aaaa";
			
			$this->ShippingProduct = $P->find("first",array("conditions" => array("$shippingProductIdField LIKE" => $shippingProductId)));

			if (!$this->ShippingProduct) return false;
		}
		
		$this->Controller->Order->OrderProduct->create();
		
		$shipping = array("OrderProduct" => 
			array(
				"product_id" => $this->ShippingProduct['Product']['id'],
				"order_id" => $order_id,
				"amount" => 1,
				"price" => $this->ShippingProduct['Product']['price'],
				"sorting" => "9999",
			)
		);
		
		return $this->Controller->Order->OrderProduct->save($shipping);
	}
	
	/**
	 * Since sometimes float is interpreted as Date we have to convert it back here... Silly shit...
	 * Jan 25 => 01.25
	 * @param unknown $val
	 */
	protected function cal2float($val) {
		if (floatval($val) == 0) {
			$values = explode(" ",$val);
			$val = floatval(date("m",strtotime($values[0])).".".$values[1]);
		}
		return floatval($val);
	}
  	
	/**
	 * * sku					-> ASSIGN TO product (!IMPORTANT! when not exactly the same write message into order_state table)
 	 * * item-price				-> price
 	 * * quantity-purchased		-> amount
 	 * 
 	 * if not found directly: ADD MESSAGE WITH product-name!!!
	 * @param unknown $row
	 * @param unknown $order_id
	 */
	protected function createOrderProduct($row,$order_id) {
		
		$this->createdOrders[$row['order-id']]['invocations']++;
		
		$orderProduct = array();
		$orderProduct['amount'] = $this->cal2float($row['quantity-purchased']);
		
		// Amazon sums the price for all items in the table. We need the single piece price.
		$priceSum = $this->cal2float($row['item-price']) / $orderProduct['amount'];
		$orderProduct['price'] = $priceSum;
		$orderProduct['product_id'] = $this->findItemNumberFromAmazonItem($row);
		$orderProduct['order_id'] = $order_id;
		$orderProduct['sorting'] = $this->createdOrders[$row['order-id']]['invocations'];
		
		// Set SKU to value, so we know that this orderProduct is not assigned yet to a product.
		if ($orderProduct['product_id'] == 0) {

			$orderProduct['sku'] = $row['sku'];

			// set for report into array
			if (
			(false === isset($this->createdOrders['new_sku'])) || 
			(false === array_search($row['sku'],$this->createdOrders["new_sku"]))
			) $this->createdOrders["new_sku"][] = $row['sku'];
			
		}
		// TODO Set SKO to OrderProduct table
		
		$this->Controller->Order->OrderProduct->create();
		$saved = $this->Controller->Order->OrderProduct->save(array("OrderProduct" => $orderProduct));
		
		// (Debug) Output
		if ($saved) {
			
			$this->createdOrders[$row['order-id']]['items_count']++;
			 
			if (isset($this->createdOrders["order_products_total"])) $this->createdOrders["order_products_total"]++;
			else $this->createdOrders["order_products_total"] = 1;
			
			if (isset($this->createdOrders["order_products_total_value"])) $this->createdOrders["order_products_total_value"] += $priceSum; 
			else $this->createdOrders["order_products_total_value"] = $priceSum;
			
		} else {

			$this->errors[] = array(
					'row' => $row,
					'order_id' => $order_id,
					'OrderProduct' => $orderProduct
			);
			
		}
				
	}

	/**
	 * Checks if ORDER was importet in other import.
	 * @param unknown $row
	 * @return boolean
	 */
	protected function orderAlreadyImported($row) {
		$amazonBaNr = $row["order-id"];

		// Check if entry exists
		if (!array_key_exists($amazonBaNr,$this->ordersInDb)) {
			
			$exists = $this->Controller->Order->find("count",array("conditions" => array("amazon_ba_no" => $amazonBaNr)));
			
			if ($exists) {
				// Order Imported once before
				$this->ordersInDb[$amazonBaNr] = true;
				return true;
			} else {
				// First order import yet 
				$this->ordersInDb[$amazonBaNr] = false;
				return false;
			}
			
		}
		
		// Return if order was imported once before or first time during this import...
		return $this->ordersInDb[$amazonBaNr];
		
	}
	
	/**
	 * Imports ImportData-Rows row by row.
	 */
	protected function importOrders() {
		
		foreach ($this->importData as $i => $importRow) {
			
			if (!$this->orderAlreadyImported($importRow)) {
				
				// Get order_id or create order (and also get order)
				if (!array_key_exists($importRow['order-id'],$this->createdOrders)) {
					$order_id = $this->createOrder($importRow);				
				} else {
					$order_id = $this->createdOrders[$importRow['order-id']]["order_id"];
				}
				$this->createOrderProduct($importRow,$order_id);
			}
		}
		
	}
	
	public function import($filename,$enterprise_id) {
		if (!file_exists($filename)) return false;
		// IMPORT THE FILE AND PARSE THE LINES
		$this->enterprise_id = $enterprise_id;
		$this->importData = $this->parseImportfile($filename);

		$this->importOrders();
	}
	
	public function getReport() {
		return $this->createdOrders;
	}
	
	public function getErrors() {
		return $this->errors;
	}
	
	public function getDuplicates() {
		$duplicates = 0;
		
		foreach ($this->ordersInDb as $orderInDb) {
			if ($orderInDb) $duplicates++;
		}
		return $duplicates;
	}
	
}
