<?
    class NETSNMPWatcher
    {
        /**
	     * Watcher Name
	     *
	     * @var string
	     */
	    public $WatcherName = "NET Usage (SNMP)";
		
		const COLOR_INBOUND = "#00cc00";
		const COLOR_OUBOUND = "#0000ff";
				
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
        	
        	$rrdCreator->addDataSource("in:COUNTER:600:U:21474836480");
        	$rrdCreator->addDataSource("out:COUNTER:600:U:21474836480");
        	
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
            return array(
            	"in" =>  ".1.3.6.1.2.1.2.2.1.10.2",  // BW in
            	"out" => ".1.3.6.1.2.1.2.2.1.16.2"   // BW out
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
            $in = $matches[0][0];
            $out = $matches[0][1];

            return array("in" => $in, "out" => $out);
        }
        
    	public function UpdateRRDDatabase($name, $data)
        {
        	$rrddbpath = $this->Path."/{$name}/NETSNMP/db.rrd";
        	
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
        			"--vertical-label" => 'Bits per second',
        			"--title" => "Network usage ({$dt})",
        			"--lower-limit" => '0',
        			"--alt-autoscale-max",
        			"--alt-autoscale-min",
        			"--rigid",
        			"--no-gridfit",
        			"--slope-mode",
        			"--x-grid" => $r["x_grid"],
        			"--end" => $r["end"],
        			"--start" => $r["start"],
        			"--width" => 440,
        			"--height" => 100,
        			"--font-render-mode" => "normal",
        			"DEF:in={$rrddbpath}:in:AVERAGE",
        			"DEF:out={$rrddbpath}:out:AVERAGE",
        			"CDEF:in_bits=in,8,*",
        			"CDEF:out_bits=out,8,*",
        			"VDEF:in_last=in_bits,LAST",
        			"VDEF:in_avg=in_bits,AVERAGE",
        			"VDEF:in_max=in_bits,MAXIMUM",
        			"VDEF:out_last=out_bits,LAST",
        			"VDEF:out_avg=out_bits,AVERAGE",
        			"VDEF:out_max=out_bits,MAXIMUM",
        			'COMMENT:<b><tt>           Current   Average   Maximum</tt></b>\\j',
        			'AREA:in_bits'.self::COLOR_INBOUND.':<tt>In\:    </tt>',
        			'GPRINT:in_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:in_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:in_max:<tt>  %4.1lf%s</tt>\n',
        			'LINE1:out_bits'.self::COLOR_OUBOUND.':<tt>Out\:   </tt>',
        			'GPRINT:out_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:out_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:out_max:<tt>  %4.1lf%s</tt>\n'
        	);
        	
        	if (file_exists(CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH))
        		$options["--font"] = "DEFAULT:0:".CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH;
        	
        	$rrdGraph->setOptions($options);
        	
        	try {
        		return $rrdGraph->save();
        	} catch (Exception $e) {
        		var_dump($e);
        	}
        	
        	/*
        	$graph = new RRDGraph(440, 100, CONFIG::$RRDTOOL_PATH);
			
        	$graph->AddDEF("in", $rrddbpath, "in", "AVERAGE");
			$graph->AddDEF("out", $rrddbpath, "out", "AVERAGE");
			
			$graph->AddCDEF("in_bits", "in,8,*");
			$graph->AddCDEF("out_bits", "out,8,*");
			
			$graph->AddVDEF("in_last", "in_bits,LAST");
            $graph->AddVDEF("in_avg", "in_bits,AVERAGE");
            $graph->AddVDEF("in_max", "in_bits,MAXIMUM");
            
            $graph->AddVDEF("out_last", "out_bits,LAST");
            $graph->AddVDEF("out_avg", "out_bits,AVERAGE");
            $graph->AddVDEF("out_max", "out_bits,MAXIMUM");
            
            $graph->AddComment('<b><tt>           Current   Average   Maximum</tt></b>\\j');
            
			$graph->AddArea("in_bits", self::COLOR_INBOUND, "<tt>In:    </tt>");
            $graph->AddGPrint("in_last", '<tt>  %4.1lf%s</tt>');
            $graph->AddGPrint("in_avg",  '<tt>  %4.1lf%s</tt>');
            $graph->AddGPrint("in_max",  '<tt>  %4.1lf%s</tt>\n');
            
            $graph->AddLine(1, "out_bits", self::COLOR_OUBOUND, "<tt>Out:   </tt>");
            $graph->AddGPrint("out_last", '<tt>  %4.1lf%s</tt>');
            $graph->AddGPrint("out_avg",  '<tt>  %4.1lf%s</tt>');
            $graph->AddGPrint("out_max",  '<tt>  %4.1lf%s</tt>\n');
            
            if (CONFIG::$RRD_DEFAULT_FONT_PATH)
            	$graph->AddFont("DEFAULT", "0", CONFIG::$RRD_DEFAULT_FONT_PATH);
            	
            $dt = date("M j, Y H:i:s");
            	
            $res = $graph->Plot($image_path, $r["start"], $r["end"], 
                            array(
                            		"--step", $r["step"],
                                    "--pango-markup",
                            		"-v", "Bits per second", 
                                    "-t", "Network usage ({$dt})",
                                    "-l", "0", 
                                    "--alt-autoscale-max",
                            		"--alt-autoscale-min",
                                    "--rigid",
                            		"--no-gridfit",
                            		"--slope-mode",
                            		"--x-grid", $r["x_grid"]
                                 )
                         );
         
             return true;
             */
        }
    }
?>