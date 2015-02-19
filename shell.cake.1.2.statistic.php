<?php
/**
 * Diese Klasse fragt Umsatzdaten eines Warenwirtschaftssystems ab und exportiert diese als CSV-Cache Dateien.
 * Der Vorteil bei der Ablage in den Cache liegt dann darin, dass alle Kumulierten Ergebnisse Tageweise vorliegen
 * und nicht ständig auf Abruf neu generiert werden müssen.
 * Als Überwachung dient dann eine Datenbankbasierte Queue, welche per Cron Ergebnisse tageweise neu generiert,
 * sobald das zugehörige Model gespeichert wurde.
 * All new Code by Andreas Kosmowicz.
 **/
class StatisticShell extends Shell {

	var $name = "Statistic";
	var $uses = array("Order", "Enterprise", "OrderProduct","StatisticUpdate");

	var $dayFrom = null;
	var $dayTo = null;
	var $days = null;
	var $updateCache = false;
	var $statisticType;


	protected function initDateRange() {
		$this->dayFrom = (isset($this->params['day'])) ? date("Y-m-d",strtotime($this->params['day'])) : date("Y-m-d");

		$this->days = (isset($this->params['days'])) ? $this->params['days'] : 1; 
		$this->dayTo = date("Y-m-d",strtotime($this->params['day']."+ ".$this->days." DAY"));

		$this->out("Statistik ab ".$this->dayFrom);
	}

	protected function getDateConditions($from = null, $to = null) {

		if (is_null($from)) $from = $this->dayFrom;
		
		if (is_null($to)) {
			$to = date("Y-m-d",strtotime($from." + 1 DAY"));
		}

		return array("Order.created >=" => $from , "Order.created <"=>$to);
	}

	protected function day($from,$to = null) {

        $time = microtime(true);
        $conditions = $this->getDateConditions($from,$to);

        $orders = $this->Order->find('list', array(
            'conditions' => $conditions,
            'fields' => array('Order.id', 'Order.enterprise_id'),
        	'recursive' => -1
        ));

        $this->out("Anzahl Bestellungen: ".count($orders));

        $enterprises = $this->Enterprise->find('list');

        $order_ids = array_keys($orders);

        // prepare statistics for enterprises
        $ent_counter = array(); // count sales by enterprise_id
        $ent_prices_sum = array(); // sum sales by enterprise_id
        $order_count = sizeof( $orders);
        for ($i = 0; $i < $order_count; $i++) {

            $ent_id = $orders[$order_ids[$i]];
            if (array_key_exists($ent_id, $ent_counter))
                $ent_counter[$ent_id]++;
            else
                $ent_counter[$ent_id] = 1;

            $order_products_foo = $this->Order->OrderProduct->find('list', array(
                'conditions' => array('order_id' => $order_ids[$i]),
                'fields' => array( 'OrderProduct.price', 'OrderProduct.amount', 'OrderProduct.id'),
            	"recursive" => -1
            ));
            
            foreach( $order_products_foo as $op_id => $value) {

                $price = array_shift( array_keys( $value));
                $amount = array_shift( $value);
                $sum = $price * $amount;
                if (array_key_exists($ent_id, $ent_prices_sum))
                    $ent_prices_sum[$ent_id]+= $sum;
                else
                    $ent_prices_sum[$ent_id] = $sum;
            }
            unset( $order_products_foo);
        }

        $ent_orders_counter = array();
        $ent_prices_ordered = array();
        $ent_ordered = array();
        $ent_sorted = $enterprises;
        sort($ent_sorted);
        for ($i = 0; $i < sizeof($enterprises); $i++) {

            $ent_id = array_search($ent_sorted[$i], $enterprises);
            $ent_ids[$i] = $ent_id;
            $ent_ordered[$i] = $enterprises[$ent_id];
            if (array_key_exists($ent_id, $ent_counter))
                $ent_orders_counter[$i] = $ent_counter[$ent_id];
            else
                $ent_orders_counter[$i] = 0;

            if (array_key_exists($ent_id, $ent_prices_sum))
                $ent_prices_ordered[$i] = $ent_prices_sum[$ent_id];
            else
                $ent_prices_ordered[$i] = 0;
        }
        
        $time = round( microtime(true)-$time, 2);

        $this->out("Berechnungszeit : $time Sekunden");

        $filename = $this->filename($from);

        $file = fopen($filename,"w");
        //fputcsv($file,$ent_ordered);			// Enterprise Names
        fputcsv($file,$ent_ids); 				// Enterprise IDs
        fputcsv($file, $ent_orders_counter);	// Enterprise Orders
        fputcsv($file,$ent_prices_ordered);		// Enterprise Umsätze
        fclose($file);

        return $filename;
	}

	protected function daily() {
	
		$this->statisticType = "enterprise_daily";

		for ($i = 0; $i < $this->days; $i++) {

			$this->out("\n");
			
			$day = date("Y-m-d",strtotime("{$this->dayFrom} + $i DAYS"));
			
			$action = "neu erstellt";

			if (!$this->updateCache && file_exists($this->filename($day))) {
				$this->out("$day überspringen (schon im Cache)");
				continue;
			} elseif (file_exists($this->filename($day)))  {
				$action = "aktualisiert";
			}

			$filename = $this->day($day);
			$this->out($day." im Cache $action.");
		}

	}

	protected function monthly() {
		$this->statisticType = "enterprise_monthly";

		$from = date("Y-m",strtotime($this->dayFrom));
		$to = date("Y-m",strtotime($this->dayFrom." + 1 MONTH"));
		$this->log("Monatliche Auswertung von ".$from." - ".$to);

		$action = "neu erstellt";

		if (!$this->updateCache && file_exists($this->filename($from))) {
			$this->out("$from überspringen (schon im Cache)");
			continue;
		} elseif (file_exists($this->filename($from)))  {
			$action = "aktualisiert";
		}

		$this->day($from, $to);
		$this->out("Monat ab $from im Cache $action.");

	}

	protected function weekly() {
		$this->statisticType = "enterprise_weekly";

		$from = date("Y-m-d",strtotime($this->dayFrom));
		$to = date("Y-m-d",strtotime($this->dayFrom." + 1 WEEK"));
		$this->log("Monatliche Auswertung von ".$from." - ".$to);

		$action = "neu erstellt";

		if (!$this->updateCache && file_exists($this->filename($from))) {
			$this->out("$from überspringen (schon im Cache)");
			continue;
		} elseif (file_exists($this->filename($from)))  {
			$action = "aktualisiert";
		}

		$this->day($from, $to);
		$this->out("Woche ab $from im Cache $action.");		
	}

	protected function filename($day) {
		return $this->tmpdir().$this->statisticType."_".$day.".cache";
	}

	protected function tmpdir() {
		$tmpdir =  TMP."statistic_cache";
		if (!is_dir($tmpdir)) mkdir($tmpdir);
		return 	"$tmpdir/";
	}

	protected function today() {
		$this->dayFrom = date("Y-m-d");
		$this->days = 1; 
		$this->dayTo = date("Y-m-d",strtotime("tomorrow"));
		$this->out("Heute auffrischen");
		$this->daily();
	}

	protected function yesterday() {
		$this->dayFrom = date("Y-m-d",strtotime("yesterday"));
		$this->days = 1; 
		$this->dayTo = date("Y-m-d");
		$this->out("Gestern auffrischen");
		$this->daily();
	}

	protected function cron() {
			$tasks = $this->StatisticUpdate->find("all",array("conditions" => array("resolved" => 0)));
			$this->out(count($tasks)." Anforderungen gefunden");

			// Always Today!
			$this->yesterday();
			$this->today();

			foreach ($tasks as $task) {
				$task = $task['StatisticUpdate'];
				if ($task['type'] == "enterprise.daily") {
					$task['resolved'] = 1;
					// Markiere als "In Bearbeitung"
					$this->StatisticUpdate->save(array("StatisticUpdate" => $task));

					$this->dayFrom = $task['arg'];

					$this->days = 1; 
					$this->dayTo = date("Y-m-d",strtotime($task['arg']."+ ".$this->days." DAY"));

					$this->daily();

					$task['resolved'] = 100;
					// Markiere als "Fertig"
					$this->StatisticUpdate->save(array("StatisticUpdate" => $task));
				} else {
					$this->out("Unbekannt: ".$task['type']." in Task [".$task['id']."]");
				}
			}

	}

	public function main() {
		$this->out("Statistikkonsole");

		// If update = true we will rewrite cache files, otherwise not.
		if (in_array("update",$this->args) || in_array("cron",$this->args)) {
			$this->updateCache = true;
			$this->out("Überschreibe Cache!");
		}

		if (in_array("cron",$this->args)) {

			$this->cron();

		} else {

			$this->initDateRange();

			if (in_array("weekly",$this->args)) {
				$this->log("für 7 Tage");
				$this->weekly();
			} elseif( in_array("monthly", $this->args)) {
				$this->log("für den gesamten Monat");
				$this->monthly();
			} else {
				$this->out("bis ".$this->dayTo." (".$this->days.") Tage");

				$this->daily();
			}

		}


	}

}
