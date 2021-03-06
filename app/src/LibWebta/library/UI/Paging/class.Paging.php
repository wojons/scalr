<?
    /**
     * This file is a part of LibWebta, PHP class library.
     *
     * LICENSE
     *
	 * This source file is subject to version 2 of the GPL license,
	 * that is bundled with this package in the file license.txt and is
	 * available through the world-wide-web at the following url:
	 * http://www.gnu.org/copyleft/gpl.html
     *
     * @category   LibWebta
     * @package    UI
     * @subpackage Paging
     * @copyright  Copyright (c) 2003-2007 Webta Inc, http://www.gnu.org/licenses/gpl.html
     * @license    http://www.gnu.org/licenses/gpl.html
     */
    
    /**
	 * @name       Paging
	 * @category   LibWebta
     * @package    UI
     * @subpackage Paging
	 * @version 1.0
	 * @author Alex Kovalyov <http://webta.net/company.html>
	 * @author Igor Sacvhenko <http://webta.net>
	 */
    class Paging extends Core
    {
		
		public $ItemsOnPage;
		public $PageNo;
		public $Total;
		public $Label;
		public $URL;
		
		/**
		* Either show Next-Previous links or not
		* @var ShowNextPrev
		* @access public
		*/
		public $ShowNextPrev;
		
		public $URLFormat;
		
		protected $Display;
		
		/**
		 * Query String to URL format
		 *
		 * @var string
		 */
		private $QS;

	    /**
	     * @ignore
	     * @param integer $page
	     * @param integer $total
	     * @param integer $pagesize
	     */
        function __construct($page=null, $total=null, $pagesize=null)
        {
			parent::__construct();
			$this->Smarty = Core::GetSmartyInstance();
			$this->Smarty->caching = false;
			
			$this->PageNo = $page ? $page : $_GET["pn"];
             if ((int)$this->PageNo == 0)
				$this->PageNo = 1;
			
			$this->ItemsOnPage = $pagesize ? $pagesize : CF_PAGING_ITEMS;
			
            $this->Total = $_GET["pt"] && !$_POST ? $_GET["pt"] : $total;
            
            $this->ShowNextPrev = 1;
			
			$file = basename($_SERVER['PHP_SELF']);
            $this->URLFormat = $file."?pn=%d&pt=%d";
            $this->SetLabel();
        }
        
        /**
         * Add URL Filter
         *
         * @param string $name
         * @param string $value
         */
        public function AddURLFilter($name, $value)
        {        	 
       		$this->URLFormat .= "&{$name}={$value}";
        }
        
		public function SetURLFormat($format)
		{
			$this->URLFormat = $format;
		}
		
        public function SetLabel($label="")
        {
            $this->Label = $label;
        }
		
		
		/**
		* Proccess data and assign Display parameters
		* @access protected
		* @return void
		*/
        public function ParseHTML()
        {

			$this->Display["title"] = $this->Label;			
			$this->Display["links"] = $this->ShowNextPrev;
			$this->Display["total"] = $this->Total;
			
            $totalpages = ((int)$this->ItemsOnPage > 0) ? ceil($this->Total / (int)$this->ItemsOnPage) : 0;
			if ($this->PageNo > $totalpages) 
				$this->PageNo = $totalpages;

            $firstpage = $this->PageNo >= 10 ? $this->PageNo - 5 : 1;
            $lastpage = $firstpage + 9;

            $lastpage = $lastpage > $totalpages ? $totalpages : $lastpage;
			
			// Generate URL
			$url = $this->URLFormat . $this->GetQueryString();
			
			if ($this->ShowNextPrev)
			{
				if ($this->PageNo > 1)
					$this->Display["prevlink"] = $this->URL.sprintf($url, $this->PageNo - 1, $this->Total);
				if ($this->PageNo < $lastpage)
					$this->Display["nextlink"] = $this->URL.sprintf($url, $this->PageNo + 1, $this->Total);
			}
			
			if ($firstpage > 1)
				$this->Display["firstpage"] = array("link" => $this->URL.sprintf($url, 1, $this->Total), "num" => "1");
			
            for ($n = $firstpage; $n <= $lastpage; $n++)
            {
				$selected = ($this->PageNo == $n);
                $this->Display["pages"][] = array("num" => $n, "selected"=>$selected, "link" => $this->URL.sprintf(urldecode($url), $n, $this->Total));
            }

            if ($lastpage < $totalpages)
				$this->Display["lastpage"] = array("link" => $this->URL.sprintf($url, $totalpages, $this->Total), "num" => $totalpages);

        }
		
		
		/**
		* Returns parsed Smarty pager
		* @access public
		* @param string $template Template file name. Default is admin/paging.tpl
		* @return string
		*/
		public function GetHTML($template = "paging.tpl")
		{
			$this->Smarty->assign($this->Display);
            return($this->Smarty->fetch($template));
		}
		

        public function GetOffset()
        {
            $retval = ($this->PageNo - 1) * $this->ItemsOnPage;
			
            if ($retval < 0)
				$retval = 0;
            return($retval);
        }


        public function GetFirst()
        {
            return min($this->GetOffset() + 1, $this->Total);
        }


        public function GetLast()
        {
            return min($this->GetFirst() + $this->ItemsOnPage - 1, $this->Total);
        }
        
        
        public final function SetFormat($format)
        {
            $this->Format = $format;
        }

        private function GetQueryString()
        {
        	if ($this->URLFormat)
        		return $this->QS;
        	return substr($this->QS, 1);
        }
        
        public function SetQueryString($qs)
        {
        	$this->QS = $qs;
        }
        
    	function GetFilterHTML($template = "table_filter.tpl", $display = false)
		{
			$this->Smarty->assign($display);
			$this->Smarty->assign($this->Display);
			
			return($this->Smarty->fetch($template));
		}
    }

?>
