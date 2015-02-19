<?php
/**
 * Diese Klasse erwetert Models um die Möglichkeit eine Aktion in eine Queue-Tabelle abzulegen,
 * welche dann eine neue Cachedatei erzeugt.
 * Sie hängt am Event beforeSave() sowie beforeDelete() des entsprechenden Models
 * All new code made by Andreas Kosmowicz
 */
class StatisticCacheBehavior extends ModelBehavior {

	protected $type = "enterprise.daily"; // Type to insert into database
	protected $twoDaysAgo = null;

	public function setup($Model, $settings) {
		$this->twoDaysAgo = strtotime(date("Y-m-d",strtotime("now - 2 days")));
	}

	protected function createCacheRequest($arg) {

		App::import("Model","StatisticUpdate");

		$StatisticUpdate = new StatisticUpdate();

		$count = $StatisticUpdate->find("count",
			array("conditions" => 
				array("AND" => 
					array(	"type" => $this->type,
						"resolved" => 0,
						"arg" => $arg
						)
					)
				)
			);

		if (!$count) {
			$StatisticUpdate->create();
			$StatisticUpdate->save(array("StatisticUpdate" => array(
	            "type" => $this->type,
	            "arg" => $arg,
	            'resolved' => 0,
	            'created' => date("Y-m-d H:i:s")
			)));
			echo "saved";
		} else {
		}
	} 

	protected function twoDaysAgo() {
		return $this->twoDaysAgo;
	}

	protected function moreThanOneDayAgo($date) {
		return ($date <= $this->twoDaysAgo);
	}

	/**
	 * Here we check if the order/order_product to be modified has a date older than one day.
	 * If yes, we will reset the cache of that date
	 **/
	public function beforeSave($Model, $created) {
		
		App::Import('Model','Order');
		
		$orderDate = false;
		
		// Needed fo comparison	
		$twoDaysAgo = $this->twoDaysAgo();

		if (!isset($Model->data['Order']) && isset($Model->data['OrderProduct'])) {
			// Is this an Order Product?

			$Order = new Order();
			$orderDate = $Order->field("created",array(array("Order.id" => $Model->data['OrderProduct']['order_id'])));
			$orderDateAsDate = date("Y-m-d",strtotime($orderDate));
			$orderDate =  strtotime($orderDateAsDate);
			
		} elseif(isset($Model->data['Order']) && isset($Model->data['Order']['id'])) {
			// Order

			$orderDate = $Model->data['Order']['created'];
			$orderDateAsDate = date("Y-m-d",strtotime($orderDate));
			$orderDate =  strtotime($orderDateAsDate);

			// Get Old Order Date

			$Order = new Order();
			$oldOrderDate = $Order->field("created",array(array("Order.id" => $Model->data['Order']['id'])));

			if ($oldOrderDate && $orderDate) {
				$oldOrderDateAsDate = date("Y-m-d",strtotime($oldOrderDate));
				$oldOrderDate = strtotime($oldOrderDateAsDate);

				if ( ($orderDateAsDate != $oldOrderDateAsDate) && $this->moreThanOneDayAgo($oldOrderDate) ) {
					$this->createCacheRequest($oldOrderDateAsDate);
				}

			}

		} elseif (isset($Model->data['Order']) && !isset($Model->data['Order']['id'])) {
			$orderDate = $Model->data['Order']['created'];
			$orderDateAsDate = date("Y-m-d",strtotime($orderDate));

			if ($this->moreThanOneDayAgo($orderDate)) {
				$this->createCacheRequest($oldOrderDateAsDate);
			}

		}

		if ($orderDate) {
			// So, if we have an order date we check if this order was placed more than two days ago.
			if ($this->moreThanOneDayAgo($orderDate)) {
				$this->createCacheRequest($orderDateAsDate);
			}

		}

	}

	/**
	 * If this is an Order we will send the Request as well
	 **/
	public function beforeDelete($model,$cascade = true) {
		if (isset($Model->data['Order']['created'])) {
			$createdAsDate = date("Y-m-d",strtotime($Model->data['Order']['created']));
			$created = strtotime($createdAsDate);
			if ($this->moreThanOneDayAgo($created))
				$this->createCacheRequest($createdAsDate);
		}
	}

}
