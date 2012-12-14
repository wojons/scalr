<?
    class MEMSNMPWatcher
    {
		private $RRD;
				
		/**
	     * Watcher Name
	     *
	     * @var string
	     */
	    public $WatcherName = "RAM Usage (SNMP)";
		
		const COLOR_MEM_SHRD = "#00FFFF";
		const COLOR_MEM_BUFF = "#3399FF";
		const COLOR_MEM_CACH = "#0000FF";
		const COLOR_MEM_FREE = "#99FF00";
		const COLOR_MEM_REAL = "#00CC00";
		const COLOR_MEM_SWAP = "#FF0000";
		
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
        	 
        	$rrdCreator->addDataSource("swap:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("swapavail:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("total:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("avail:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("free:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("shared:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("buffer:GAUGE:600:U:U");
        	$rrdCreator->addDataSource("cached:GAUGE:600:U:U");
        	
        	 
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
            	"swap" => ".1.3.6.1.4.1.2021.4.3.0", // Swap
            	"swapavail" => ".1.3.6.1.4.1.2021.4.4.0", // SwapAvail
            	"total" => ".1.3.6.1.4.1.2021.4.5.0", // Total
            	"avail" => ".1.3.6.1.4.1.2021.4.6.0", // Avail
            	"free" => ".1.3.6.1.4.1.2021.4.11.0", // Free
            	"shared" => ".1.3.6.1.4.1.2021.4.13.0", // Shared
            	"buffer" => ".1.3.6.1.4.1.2021.4.14.0", // Buffer
            	"cached" => ".1.3.6.1.4.1.2021.4.15.0" // Cached
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
            
            
            $MEMSwap = $matches[0][0];
            $MEMSwapAvail = $matches[0][1];
            $MEMTotal = $matches[0][2];
            $MEMAvail = $matches[0][3];
			$MEMFree = $matches[0][4];
            $MEMShared = $matches[0][5];
            $MEMBuffer = $matches[0][6];
            $MEMCached = $matches[0][7];
            
            $data = array(
            	"swap" => $MEMSwap, 
            	"swapavail" => $MEMSwapAvail, 
            	"total" => $MEMTotal, 
            	"avail" => $MEMAvail, 
            	"free" => $MEMFree, 
            	"shared" =>$MEMShared, 
            	"buffer" => $MEMBuffer, 
            	"cached" => $MEMCached
            );
            
            return $data;
        }
        
    	public function UpdateRRDDatabase($name, $data)
        {
        	$rrddbpath = $this->Path."/{$name}/MEMSNMP/db.rrd";
        	
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
        			"--vertical-label" => 'Memory Usage',
        			"--title" => "Memory Usage ({$dt})",
        			"--lower-limit" => '0',
        			"--base" => 1024,
        			"--alt-autoscale-max",
        			"--alt-autoscale-min",
        			"--rigid",
        			"--no-gridfit",
        			"--slope-mode",
        			"--x-grid" => $r["x_grid"],
        			"--end" => $r["end"],
        			"--start" => $r["start"],
        			"--width" => 440,
        			"--height" => 180,
        			"--font-render-mode" => "normal",
        			"DEF:mem1={$rrddbpath}:swap:AVERAGE",
        			"DEF:mem2={$rrddbpath}:swapavail:AVERAGE",
        			"DEF:mem3={$rrddbpath}:total:AVERAGE",
        			"DEF:mem4={$rrddbpath}:avail:AVERAGE",
        			"DEF:mem5={$rrddbpath}:free:AVERAGE",
        			"DEF:mem6={$rrddbpath}:shared:AVERAGE",
        			"DEF:mem7={$rrddbpath}:buffer:AVERAGE",
        			"DEF:mem8={$rrddbpath}:cached:AVERAGE",
        			
        			"CDEF:swap_total=mem1,1024,*",
        			"VDEF:swap_total_min=swap_total,MINIMUM",
        			"VDEF:swap_total_last=swap_total,LAST",
        			"VDEF:swap_total_avg=swap_total,AVERAGE",
        			"VDEF:swap_total_max=swap_total,MAXIMUM",
        			
        			"CDEF:swap_avail=mem2,1024,*",
        			"VDEF:swap_avail_min=swap_avail,MINIMUM",
        			"VDEF:swap_avail_last=swap_avail,LAST",
        			"VDEF:swap_avail_avg=swap_avail,AVERAGE",
        			"VDEF:swap_avail_max=swap_avail,MAXIMUM",
        			
        			"CDEF:swap_used=swap_total,swap_avail,-",
        			"VDEF:swap_used_min=swap_used,MINIMUM",
        			"VDEF:swap_used_last=swap_used,LAST",
        			"VDEF:swap_used_avg=swap_used,AVERAGE",
        			"VDEF:swap_used_max=swap_used,MAXIMUM",
        			
        			"CDEF:mem_total=mem3,1024,*",
        			"VDEF:mem_total_min=mem_total,MINIMUM",
        			"VDEF:mem_total_last=mem_total,LAST",
        			"VDEF:mem_total_avg=mem_total,AVERAGE",
        			"VDEF:mem_total_max=mem_total,MAXIMUM",
        			
        			"CDEF:mem_avail=mem4,1024,*",
        			"VDEF:mem_avail_min=mem_avail,MINIMUM",
        			"VDEF:mem_avail_last=mem_avail,LAST",
        			"VDEF:mem_avail_avg=mem_avail,AVERAGE",
        			"VDEF:mem_avail_max=mem_avail,MAXIMUM",
        			
        			"CDEF:mem_free=mem5,1024,*",
        			"VDEF:mem_free_min=mem_free,MINIMUM",
        			"VDEF:mem_free_last=mem_free,LAST",
        			"VDEF:mem_free_avg=mem_free,AVERAGE",
        			"VDEF:mem_free_max=mem_free,MAXIMUM",
        			
        			"CDEF:mem_shared=mem6,1024,*",
        			"VDEF:mem_shared_min=mem_shared,MINIMUM",
        			"VDEF:mem_shared_last=mem_shared,LAST",
        			"VDEF:mem_shared_avg=mem_shared,AVERAGE",
        			"VDEF:mem_shared_max=mem_shared,MAXIMUM",
        			
        			"CDEF:mem_buffer=mem7,1024,*",
        			"VDEF:mem_buffer_min=mem_buffer,MINIMUM",
        			"VDEF:mem_buffer_last=mem_buffer,LAST",
        			"VDEF:mem_buffer_avg=mem_buffer,AVERAGE",
        			"VDEF:mem_buffer_max=mem_buffer,MAXIMUM",
        			
        			"CDEF:mem_cached=mem8,1024,*",
        			"VDEF:mem_cached_min=mem_cached,MINIMUM",
        			"VDEF:mem_cached_last=mem_cached,LAST",
        			"VDEF:mem_cached_avg=mem_cached,AVERAGE",
        			"VDEF:mem_cached_max=mem_cached,MAXIMUM",
        			
        			'COMMENT:<b><tt>                        Minimum       Current       Average      Maximum</tt></b>\\j',
        			
        			'AREA:mem_shared'.self::COLOR_MEM_SHRD.':<tt>Shared        </tt>',
        			'GPRINT:swap_total_min:<tt>  %4.1lf%s</tt>',
        			'GPRINT:swap_total_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:swap_total_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:swap_total_max:<tt>  %4.1lf%s</tt>\\j',
        			
        			'AREA:mem_buffer'.self::COLOR_MEM_BUFF.':<tt>Buffer         </tt>',
        			'GPRINT:mem_buffer_min:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_buffer_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_buffer_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_buffer_max:<tt>  %4.1lf%s</tt>\\j',
        			
        			'AREA:mem_cached'.self::COLOR_MEM_CACH.':<tt>Cached        </tt>:STACK',
        			'GPRINT:mem_cached_min:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_cached_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_cached_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_cached_max:<tt>  %4.1lf%s</tt>\\j',
        			
        			'AREA:mem_free'.self::COLOR_MEM_FREE.':<tt>Free           </tt>:STACK',
        			'GPRINT:mem_free_min:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_free_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_free_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_free_max:<tt>  %4.1lf%s</tt>\\j',
        			
        			'AREA:mem_avail'.self::COLOR_MEM_REAL.':<tt>Real           </tt>:STACK',
        			'GPRINT:mem_avail_min:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_avail_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_avail_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:mem_avail_max:<tt>  %4.1lf%s</tt>\\j',
        			
        			'LINE1:swap_used'.self::COLOR_MEM_SWAP.':<tt>Swap In Use  </tt>:STACK',
        			'GPRINT:swap_used_min:<tt>  %4.1lf%s</tt>',
        			'GPRINT:swap_used_last:<tt>  %4.1lf%s</tt>',
        			'GPRINT:swap_used_avg:<tt>  %4.1lf%s</tt>',
        			'GPRINT:swap_used_max:<tt>  %4.1lf%s</tt>\\j'
			);
        	
        	if (file_exists(CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH))
        		$options["--font"] = "DEFAULT:0:".CONFIG::$STATISTICS_RRD_DEFAULT_FONT_PATH;
        	
        	$rrdGraph->setOptions($options);
        	
        	try {
        		$retval =  $rrdGraph->save();
        	} catch (Exception $e) {
        		var_dump($e);
        	}

        	return $retval;
        	
        	
        	/*
        	$graph = new RRDGraph(440, 180, CONFIG::$RRDTOOL_PATH);
        	
			$graph->AddDEF("mem1", $rrddbpath, "swap", "AVERAGE");
			$graph->AddDEF("mem2", $rrddbpath, "swapavail", "AVERAGE");
			$graph->AddDEF("mem3", $rrddbpath, "total", "AVERAGE");
			$graph->AddDEF("mem4", $rrddbpath, "avail", "AVERAGE");
			$graph->AddDEF("mem5", $rrddbpath, "free", "AVERAGE");
			$graph->AddDEF("mem6", $rrddbpath, "shared", "AVERAGE");
			$graph->AddDEF("mem7", $rrddbpath, "buffer", "AVERAGE");
			$graph->AddDEF("mem8", $rrddbpath, "cached", "AVERAGE");
            
            $graph->AddCDEF("swap_total", "mem1,1024,*");
            $graph->AddVDEF("swap_total_min", "swap_total,MINIMUM");
            $graph->AddVDEF("swap_total_last", "swap_total,LAST");
            $graph->AddVDEF("swap_total_avg", "swap_total,AVERAGE");
            $graph->AddVDEF("swap_total_max", "swap_total,MAXIMUM");
            
            
            $graph->AddCDEF("swap_avail", "mem2,1024,*");
            $graph->AddVDEF("swap_avail_tot", "swap_avail,LAST");
            $graph->AddVDEF("swap_avail_min", "swap_avail,MINIMUM");
            $graph->AddVDEF("swap_avail_last", "swap_avail,LAST");
            $graph->AddVDEF("swap_avail_avg", "swap_avail,AVERAGE");
            $graph->AddVDEF("swap_avail_max", "swap_avail,MAXIMUM");
            
            $graph->AddCDEF("swap_used", "swap_total,swap_avail,-");
            $graph->AddVDEF("swap_used_min", "swap_used,MINIMUM");
            $graph->AddVDEF("swap_used_last", "swap_used,LAST");
            $graph->AddVDEF("swap_used_avg", "swap_used,AVERAGE");
            $graph->AddVDEF("swap_used_max", "swap_used,MAXIMUM");
            
            
            $graph->AddCDEF("mem_total", "mem3,1024,*");
            $graph->AddVDEF("mem_total_min", "mem_total,MINIMUM");
            $graph->AddVDEF("mem_total_last", "mem_total,LAST");
            $graph->AddVDEF("mem_total_avg", "mem_total,AVERAGE");
            $graph->AddVDEF("mem_total_max", "mem_total,MAXIMUM");
            
            $graph->AddCDEF("mem_avail", "mem4,1024,*");
            $graph->AddVDEF("mem_avail_min", "mem_avail,MINIMUM");
            $graph->AddVDEF("mem_avail_last", "mem_avail,LAST");
            $graph->AddVDEF("mem_avail_avg", "mem_avail,AVERAGE");
            $graph->AddVDEF("mem_avail_max", "mem_avail,MAXIMUM");
            
            $graph->AddCDEF("mem_free", "mem5,1024,*");
            $graph->AddVDEF("mem_free_min", "mem_free,MINIMUM");
            $graph->AddVDEF("mem_free_last", "mem_free,LAST");
            $graph->AddVDEF("mem_free_avg", "mem_free,AVERAGE");
            $graph->AddVDEF("mem_free_max", "mem_free,MAXIMUM");
            
            $graph->AddCDEF("mem_shared", "mem6,1024,*");
            $graph->AddVDEF("mem_shared_min", "mem_shared,MINIMUM");
            $graph->AddVDEF("mem_shared_last", "mem_shared,LAST");
            $graph->AddVDEF("mem_shared_avg", "mem_shared,AVERAGE");
            $graph->AddVDEF("mem_shared_max", "mem_shared,MAXIMUM");
            
            $graph->AddCDEF("mem_buffer", "mem7,1024,*");
            $graph->AddVDEF("mem_buffer_min", "mem_buffer,MINIMUM");
            $graph->AddVDEF("mem_buffer_last", "mem_buffer,LAST");
            $graph->AddVDEF("mem_buffer_avg", "mem_buffer,AVERAGE");
            $graph->AddVDEF("mem_buffer_max", "mem_buffer,MAXIMUM");
            
            $graph->AddCDEF("mem_cached", "mem8,1024,*");
            $graph->AddVDEF("mem_cached_min", "mem_cached,MINIMUM");
            $graph->AddVDEF("mem_cached_last", "mem_cached,LAST");
            $graph->AddVDEF("mem_cached_avg", "mem_cached,AVERAGE");
            $graph->AddVDEF("mem_cached_max", "mem_cached,MAXIMUM");
            
            
            
            $graph->AddComment('<b><tt>                      Minimum       Current       Average       Maximum</tt></b>\\j');
            
            $graph->AddArea("mem_shared", self::COLOR_MEM_SHRD, "<tt>Shared        </tt>");
            $graph->AddGPrint("swap_total_min",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("swap_total_last", '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("swap_total_avg",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("swap_total_max",  '<tt>%4.1lf %s</tt>\\j');
            
            $graph->AddArea("mem_buffer", self::COLOR_MEM_BUFF, "<tt>Buffer        </tt>", "STACK");
            $graph->AddGPrint("mem_buffer_min",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_buffer_last", '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_buffer_avg",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_buffer_max",  '<tt>%4.1lf %s</tt>\\j');
            
            $graph->AddArea("mem_cached", self::COLOR_MEM_CACH, "<tt>Cached        </tt>", "STACK");
            $graph->AddGPrint("mem_cached_min",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_cached_last", '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_cached_avg",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_cached_max",  '<tt>%4.1lf %s</tt>\\j');
            
            
            $graph->AddArea("mem_free", self::COLOR_MEM_FREE,   "<tt>Free          </tt>", "STACK");
            $graph->AddGPrint("mem_free_min",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_free_last", '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_free_avg",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_free_max",  '<tt>%4.1lf %s</tt>\\j');
            
            $graph->AddArea("mem_avail", self::COLOR_MEM_REAL,  "<tt>Real          </tt>", "STACK");
            $graph->AddGPrint("mem_avail_min",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_avail_last", '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_avail_avg",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("mem_avail_max",  '<tt>%4.1lf %s</tt>\\j');
            //$graph->AddGPrint("swap_avail_tot", '       Mem Total: %4.1lf%S\\j');
            
           $graph->AddLine(1, "swap_used", self::COLOR_MEM_SWAP,"<tt>Swap In Use   </tt>");
            $graph->AddGPrint("swap_used_min",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("swap_used_last", '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("swap_used_avg",  '<tt>%4.1lf %s</tt>');
            $graph->AddGPrint("swap_used_max",  '<tt>%4.1lf %s</tt>\\j');
           // $graph->AddGPrint("swap_used_last", '            Swap Total:%4.1lf%S\\j');
            
            if (CONFIG::$RRD_DEFAULT_FONT_PATH)
            	$graph->AddFont("DEFAULT", "0", CONFIG::$RRD_DEFAULT_FONT_PATH);
            	
            $dt = date("M j, Y H:i:s");
            	
            //
            // Plot graphics
            //   
            $res = $graph->Plot($image_path, $r["start"], $r["end"], 
                            array(
                            		"--step", $r["step"],
                            		"--pango-markup",
                            		"-v", "Memory Usage", 
                                    "-t", "Memory Usage ({$dt})",
                                    "-l", "0", 
                                    "-b", "1024",
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