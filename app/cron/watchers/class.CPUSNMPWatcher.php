<?
    class CPUSNMPWatcher
    {
        /**
	     * Watcher Name
	     *
	     * @var string
	     */
	    public $WatcherName = "CPU Usage (SNMP)";
		
		const COLOR_CPU_USER = "#eacc00";
		const COLOR_CPU_SYST = "#ea8f00";
		const COLOR_CPU_NICE = "#ff3932";
		const COLOR_CPU_IDLE = "#fafdce";
		
		private $RRD;
		
		/**
		 * Constructor
		 *
		 */
    	function __construct($SNMPTree, $path)
		{
		      $this->Path = $path;
		      $this->SNMPTree = $SNMPTree;
		}
        
        /**
         * This method is called after watcher assigned to node
         *
         */
        public function CreateDatabase($rrddbpath)
        {            
            @mkdir(dirname($rrddbpath), 0777, true);
            
            $rrdCreator = new RRDCreator($rrddbpath, "-1m", 180);
            
            $rrdCreator->addDataSource("user:COUNTER:600:U:U");
            $rrdCreator->addDataSource("system:COUNTER:600:U:U");
            $rrdCreator->addDataSource("nice:COUNTER:600:U:U");
            $rrdCreator->addDataSource("idle:COUNTER:600:U:U");
            
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

    	public function GetOIDs()
        {
            //
            // Add data to rrd
            //    
            return array(
            	"user" => ".1.3.6.1.4.1.2021.11.50.0", // User
            	"system" => ".1.3.6.1.4.1.2021.11.52.0", // System
            	"nice" => ".1.3.6.1.4.1.2021.11.51.0", // Nice
            	"idle" => ".1.3.6.1.4.1.2021.11.53.0" // Idle
           	);
        }
        
        /**
         * Retrieve data from node
         *
         */
        public function RetreiveData($name)
        {
            //
            // Add data to rrd
            //    
            preg_match_all("/[0-9]+/si", $this->SNMPTree->Get($this->GetOIDs()), $matches);
            $CPURawUser = $matches[0][0];
            $CPURawSystem = $matches[0][1];
            $CPURawNice = $matches[0][2];
            $CPURawIdle = $matches[0][3];
			
            return array("user" => $CPURawUser, "system" => $CPURawSystem, "nice" => $CPURawNice, "idle" => $CPURawIdle);
        }
        
    	public function UpdateRRDDatabase($name, $data)
        {
        	$rrddbpath = "{$this->Path}/{$name}/CPUSNMP/db.rrd";
        	
        	if (!file_exists($rrddbpath))
        		$this->CreateDatabase($rrddbpath);
        	
        	$data = array_map("ceil", $data);
        	
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
        		"--vertical-label" => 'Percent CPU Utilization',
       			"--title" => "CPU Utilization ({$dt})",
       			"--upper-limit" => 100,
       			"--alt-autoscale-max",
       			"--alt-autoscale-min",
       			"--rigid",
       			"--no-gridfit",
       			"--slope-mode",
        		"--x-grid" => $r["x_grid"],
        		"--end" => $r["end"],
        		"--start" => $r["start"],
       			"--width" => 440,
       			"--height" => 160,
       			"--font-render-mode" => "normal",
       			"DEF:a={$rrddbpath}:user:AVERAGE",
       			"DEF:b={$rrddbpath}:system:AVERAGE",
       			"DEF:c={$rrddbpath}:nice:AVERAGE",
       			"DEF:d={$rrddbpath}:idle:AVERAGE",
       			"CDEF:total=a,b,c,d,+,+,+",
        		"CDEF:a_perc=a,total,/,100,*",
        		"VDEF:a_perc_last=a_perc,LAST",
        		"VDEF:a_perc_avg=a_perc,AVERAGE",
       			"VDEF:a_perc_max=a_perc,MAXIMUM",
       			"CDEF:b_perc=b,total,/,100,*",
       			"VDEF:b_perc_last=b_perc,LAST",
       			"VDEF:b_perc_avg=b_perc,AVERAGE",
       			"VDEF:b_perc_max=b_perc,MAXIMUM",
       			"CDEF:c_perc=c,total,/,100,*",
       			"VDEF:c_perc_last=c_perc,LAST",
       			"VDEF:c_perc_avg=c_perc,AVERAGE",
        		"VDEF:c_perc_max=c_perc,MAXIMUM",
        		"CDEF:d_perc=d,total,/,100,*",
        		"VDEF:d_perc_last=d_perc,LAST",
       			"VDEF:d_perc_avg=d_perc,AVERAGE",
       			"VDEF:d_perc_max=d_perc,MAXIMUM",
       			'COMMENT:<b><tt>               Current    Average    Maximum</tt></b>\j',
       			'AREA:a_perc#eacc00:<tt>user    </tt>',
       			'GPRINT:a_perc_last:<tt>    %3.0lf%%</tt>',
       			'GPRINT:a_perc_avg:<tt>     %3.0lf%%</tt>',
       			'GPRINT:a_perc_max:<tt>     %3.0lf%%</tt>\n',
       			'AREA:b_perc#ea8f00:<tt>system  </tt>:STACK',
        		'GPRINT:b_perc_last:<tt>    %3.0lf%%</tt>',
        		'GPRINT:b_perc_avg:<tt>     %3.0lf%%</tt>',
        		'GPRINT:b_perc_max:<tt>     %3.0lf%%</tt>\n',
       			'AREA:c_perc#ff3932:<tt>nice    </tt>:STACK',
       			'GPRINT:c_perc_last:<tt>    %3.0lf%%</tt>',
       			'GPRINT:c_perc_avg:<tt>     %3.0lf%%</tt>',
       			'GPRINT:c_perc_max:<tt>     %3.0lf%%</tt>\n',
       			'AREA:d_perc#fafdce:<tt>idle    </tt>:STACK',
       			'GPRINT:d_perc_last:<tt>    %3.0lf%%</tt>',
       			'GPRINT:d_perc_avg:<tt>     %3.0lf%%</tt>',
       			'GPRINT:d_perc_max:<tt>     %3.0lf%%</tt>\n'
        	);
        	
        	if (file_exists(CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH))
        		$options["--font"] = "DEFAULT:0:".CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH;
        	
        	$rrdGraph->setOptions($options);
        	
        	try {
        		return $rrdGraph->save();
        	} catch (Exception $e) {
        		var_dump($e);
        	}
        }
    }
?>