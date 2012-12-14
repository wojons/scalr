<?
    class ServersNumWatcher
    {
        /**
	     * Watcher Name
	     *
	     * @var string
	     */
	    public $WatcherName = "Servers";
		
		const COLOR_RUNNING_SERVERS = "#0000FF";

		
		/**
		 * Constructor
		 *
		 */
    	function __construct($SNMPTree, $path)
		{
		      $this->Path = $path;
		}
        
		public function GetOIDs()
		{
			return array();
		}
		
        /**
         * This method is called after watcher assigned to node
         *
         */
        public function CreateDatabase($rrddbpath)
        {            
            @mkdir(dirname($rrddbpath), 0777, true);
            
            $rrdCreator = new RRDCreator($rrddbpath, "-1m", 180);
            
            $rrdCreator->addDataSource("s_running:GAUGE:600:U:U");
            
            $rrdCreator->addArchive("AVERAGE:0.5:1:800");
            $rrdCreator->addArchive("AVERAGE:0.5:6:800");
            $rrdCreator->addArchive("AVERAGE:0.5:24:800");
            $rrdCreator->addArchive("AVERAGE:0.5:288:800");
            
            $rrdCreator->addArchive("MAX:0.5:1:800");
            $rrdCreator->addArchive("MAX:0.5:6:800");
            $rrdCreator->addArchive("MAX:0.5:24:800");
            $rrdCreator->addArchive("MAX:0.5:288:800");
            
            $rrdCreator->addArchive("LAST:0.5:1:800");
            $rrdCreator->addArchive("LAST:0.5:6:800");
            $rrdCreator->addArchive("LAST:0.5:24:800");
            $rrdCreator->addArchive("LAST:0.5:288:800");
            
            $retval = $rrdCreator->save();
            
            @chmod($rrddbpath, 0777);
            
            return $retval;
        }
        
        /**
         * Retrieve data from node
         *
         */
        public function RetreiveData($name)
        {
            return array();
        }
        
    	public function UpdateRRDDatabase($name, $data)
        {
        	$rrddbpath = "{$this->Path}/{$name}/SERVERS/db.rrd";
        	
        	if (!file_exists($rrddbpath))
        		$this->CreateDatabase($rrddbpath);
        	
        	$rrdUpdater = new RRDUpdater($rrddbpath);
        	$rrdUpdater->update($data);
        }
        
        /**
         * Plot graphic
         *
         * @param integer $serverid
         */
        public static function PlotGraphic($rrddbpath, $image_path, $r)
        {		
        	$dt = date("M j, Y H:i:s");
        	
        	$rrdGraph = new RRDGraph($image_path);
        	
        	$options = array(
        		"--step" => $r["step"],
        		"--pango-markup",
        		"--vertical-label" => 'Servers',
       			"--title" => "Servers count ({$dt})",
       			"--alt-autoscale-max",
       			"--alt-autoscale-min",
       			"--lower-limit" => 0,
       			"--y-grid" => "1:1",
       			"--units-exponent" => '0',
       			"--rigid",
       			"--no-gridfit",
       			"--slope-mode",
        		"--x-grid" => $r["x_grid"],
        		"--end" => $r["end"],
        		"--start" => $r["start"],
       			"--width" => 440,
       			"--height" => 100,
       			"--font-render-mode" => "normal",
       			"DEF:s_running={$rrddbpath}:s_running:AVERAGE",
        		"VDEF:s_running_last=s_running,LAST",
        		"VDEF:s_running_avg=s_running,AVERAGE",
       			"VDEF:s_running_max=s_running,MAXIMUM",
       			"VDEF:s_running_min=s_running,MINIMUM",
       			'COMMENT:<b><tt>                     Current    Average    Maximum    Minimum</tt></b>\j',
       			'LINE1:s_running'.self::COLOR_RUNNING_SERVERS.':<tt>Running servers </tt>',
       			'GPRINT:s_running_last:<tt>    %3.0lf</tt>',
       			'GPRINT:s_running_avg:<tt>     %3.0lf</tt>',
       			'GPRINT:s_running_max:<tt>     %3.0lf</tt>',
       			'GPRINT:s_running_min:<tt>     %3.0lf</tt>\n'
        	);
        	
        	try {
	        	if (file_exists(CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH))
	        		$options["--font"] = "DEFAULT:0:".CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH;
	        	
	        	$rrdGraph->setOptions($options);
	        	
	        	return $rrdGraph->save();
        	} catch (Exception $e) {
        		var_dump($e->getMessage());
        	}
        }
    }
?>