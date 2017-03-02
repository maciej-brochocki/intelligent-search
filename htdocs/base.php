<?php
class Base
{
	protected $values;
	protected $css;
	protected $js;
	protected $smarty=0;
	protected $template_name="base.tpl";
	protected $do_render=1;

	public function __construct()
	{
		$this->values=Array();
		$this->css=Array();
		$this->js=Array();

		if($this->smarty===0)
		{
			include(SMARTY_DIR.'Smarty.class.php');
			$this->smarty = new Smarty();
			$this->smarty->setTemplateDir(SMARTY_DIR.'../templates');
			$this->smarty->setCompileDir(SMARTY_DIR.'../templates_c');
			$this->smarty->setCacheDir(SMARTY_DIR.'../cache');
			$this->smarty->setConfigDir(SMARTY_DIR.'../configs');
			$this->smarty->setPluginsDir(SMARTY_DIR.'../plugins');
		}
	}

	public function __call($name,$args)
	{
		$this->{'template_'.$name}();
	}

	public function __set($name,$value)
	{
		$this->values[$name]=$value;
	}

	public function __get($name)
	{
		return $values[$name];
	}

	public function addCss($val)
	{
		$this->css[]=$val;
	}

	public function addJs($val)
	{
		$this->js[]=$val;
	}

	public function assign_class($name,$obj)
	{
		$this->smarty->assign_by_ref($name, $obj);
	}

	public function no_render()
	{
		$this->do_render=0;
	}

	public function assign()
	{
		foreach($this->values as $key=>$val)
		{
			$this->smarty->assign($key,$val);
		}
	}

	public function render()
	{
		$this->assign();
		$this->smarty->assign("css",$this->css);
		$this->smarty->assign("js",$this->js);
		if($this->do_render==1)
		{
			$this->smarty->display($this->template_name);
		}
	}
}
?>