<?php

class acf_takeout {
	protected $modulesFolder = 'modules';
	protected $defaultModules = array();

	protected $acfLayouts = array();
	protected $acfGroupId;
	protected $acfGroupName = 'Content';
	protected $acfModulesId;
	protected $acfModulesName = 'Modules';
	protected $acfModulesSingular;

	protected $modules = array();
	protected $takeout = array();
	protected $notices = array();

	public function __construct(){
		if (is_file($this->modulesFolder.'/defaults.json')){
			$this->defaultModules = json_decode(file_get_contents($this->modulesFolder.'/defaults.json'));
		}

		$this->acfGroupId = uniqid('group_');
		$this->acfModulesId = uniqid('field_');
		$this->acfModulesSingular = substr($this->acfModulesName, 0, -1); // TODO: make this more robust?

		if (!class_exists('ZipArchive')){
			@$this->notices['error'][] = 'libzip PHP extension not installed. We won\'t be able to serve you any files without it....';
		}

		$this->discover_modules();

		if ($_SERVER['REQUEST_METHOD'] === 'POST'){
			if (isset($_POST['acfLayouts']) && !empty($_POST['acfLayouts'])){
				$this->start_cooking();
			} else {
				@$this->notices['error'][] = 'Sorry. If you don\'t order any food, we don\'t have anything to cook for you....';
			}
		}

		if (isset($_REQUEST['defaults'])){
			$this->the_usual();
		}
	}

	protected function start_cooking(){
		foreach (array('acfGroupName', 'acfModulesName') as $var){
			if (isset($_POST[$var]) && trim($_POST[$var])){
				$this->$var = trim($_POST[$var]);
			}
		}

		$this->takeout = array_map('base64_decode', array_keys($_POST['acfLayouts']));
		$this->make_acf_layouts();

		$this->zip_and_output();
	}

	protected function the_usual(){
		$this->takeout = $this->defaultModules;
		$this->make_acf_layouts();

		$this->zip_and_output();
	}

	protected function make_acf_layouts(){
		if (!$this->takeout){ return false; }

		foreach ($this->takeout as $layout){
			$json = $this->get_module_json($layout);

			$new_key = uniqid('layout_');
			$json->key = $new_key;
			$this->acfLayouts[$new_key] = $json;
		}
	}

	protected function make_acf_group_json(){
		$json = array(
			'key' => $this->acfGroupId,
			'title' => $this->acfGroupName,
			'fields' => array(array(
				'key' => $this->acfModulesId,
				'label' => $this->acfModulesName,
				'name' => slugify($this->acfModulesName, 1),
				'type' => 'flexible_content',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'layouts' => $this->acfLayouts,
				'button_label' => 'Add '.$this->acfModulesSingular,
				'min' => '',
				'max' => '',
			)),
			'location' => array(
				array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'page',
					),
				),
			),
			'menu_order' => 20,
			'position' => 'normal',
			'style' => 'default',
			'label_placement' => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen' => '',
			'active' => 1,
			'description' => '',
			'modified' => time(),
		);

		return json_encode($json, JSON_PRETTY_PRINT);
	}

	protected function zip_and_output(){
		if (!$this->takeout){ return false; }

		require_once 'ZipExtension.php';
		$zip = new ZipExtension();

		$singular = slugify($this->acfModulesSingular);
		$filetypes = array('php', 'scss', 'css', 'js');

		$files = array();
		foreach ($this->takeout as $folder){
			$json = $this->get_module_json($folder);
			if (!$json){ continue; }

			$slug = $json->name;
			$name = $json->label;

			$path = $this->modulesFolder.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR;

			$layout_contents = $this->modules[$folder];
			foreach ($layout_contents as $file){
				$ext = substr(strrchr($file, '.'), 1);
				if (!in_array($ext, $filetypes)){ continue; }

				$contents = file_get_contents($path.$file);
				if (!$contents){ continue; }

				switch ($ext) {
					case 'css':
					case 'scss':
						$files['src/css/layouts/'.$singular.'-'.slugify($name).'.scss'] = $contents;
						break;

					case 'js':
						$files['src/js/'.slugify($name).'/'.$file] = $contents;
						break;

					case 'php':
						$files['templates/'.$singular.'-'.$slug.'.php'] = $contents;
						break;
				}
			}
		}
		$files['src/acf-fields/'.$this->acfGroupId.'.json'] = $this->make_acf_group_json();
		$files['page.php'] = $this->make_page_php($singular);

		// zen_debug($files);

		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="acf-takeout.zip"');
		header('Content-Description: Some pre-cooked ACF modules');

		echo $zip->createFile(array_values($files), array_keys($files));
	}

	protected function make_page_php($singular){
		return
'<?php
get_header();

if ( !post_password_required() ){
	if ( have_posts() ) while ( have_posts() ) : the_post();

		echo \'<main class="site-main" role="main">\';

		get_template_part(\'templates/heroes\', \'\');

		if (have_rows(\''.slugify($this->acfModulesName, 1).'\')){
			while (have_rows(\''.slugify($this->acfModulesName, 1).'\')){
				the_row();
				get_template_part(\'templates/'.$singular.'\', get_row_layout());
			}
		} else {
			?>
			<article class="section content-width">
				<h2 class="h1 text-center"><?php the_title(); ?></h2>
				<?php the_content(); ?>
			</article>
			<?php
		}

		echo \'</main>\';

	endwhile;
} else {
	echo get_the_password_form();
}

get_footer(); ?>';
	}

	public function get_modules(){
		return array_keys($this->modules);
	}

	protected function discover_modules(){
		// ignore directories without a JSON file
		$modules = array_filter($this->ls($this->modulesFolder), array($this, 'check_has_json_and_php'));

		// ignore directories with invalid JSON
		foreach ($modules as $folder => $files){
			$json = $this->get_json_from_folder($folder, $files);
			if (!$this->check_valid_json($json)){
				unset($modules[$folder]);
				continue;
			}
		}

		ksort($modules);

		$this->modules = $modules;
	}

	protected function check_has_json_and_php($files){
		if (!$files || !is_array($files)){ return false; }

		return ($this->check_has_json($files) && $this->check_has_php($files));
	}

	protected function is_filename_json($filename){
		return (substr($filename, -5) === '.json');
	}

	protected function is_filename_php($filename){
		return (substr($filename, -4) === '.php');
	}

	protected function check_has_json($files){
		foreach ($files as $file){
			if ($this->is_filename_json($file)){
				return true;
			}
		}

		return false;
	}

	protected function check_has_php($files){
		foreach ($files as $file){
			if ($this->is_filename_php($file)){
				return true;
			}
		}

		return false;
	}

	protected function get_json_from_folder($folder, $files){
		$jsonFile = array_filter($files, array($this, 'is_filename_json'));
		if (!$jsonFile || !isset($jsonFile[0])){ return false; }

		$path = $this->modulesFolder.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$jsonFile[0];
		if (!is_file($path)){ return false; }

		$contents = file_get_contents($path);
		if (!$contents){ return false; }

		$json = json_decode($contents);
		// zen_debug($json);

		return $json;
	}

	protected function check_valid_json($json){
		if (!$json || !is_object($json)) { return false; }

		$required_keys = array('name', 'label', 'sub_fields');
		foreach ($required_keys as $key){
			if (!isset($json->$key)){
				@$this->notices['error'][] = '"'.$folder.'" has malformed JSON [required keys missing: '.$key.']';
				return false;
			}
		}

		if (!is_array($json->sub_fields) || !count($json->sub_fields)){
			@$this->notices['error'][] = '"'.$folder.'" has malformed JSON [no sub-fields found]';
			return false;
		}

		return true;
	}

	protected function ls($dir){
		// safety first
		if (!is_dir($dir)){
			return false;
		}

		$contents = array();
		$ignoreables = array('.','..','.DS_Store','thumbs.db');

		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $splFileInfo) {
			$name = $splFileInfo->getFilename();
			if (!in_array($name, $ignoreables)){
				$path = array($name);
				if ($splFileInfo->isDir()){
					$path = array($name => array());
				}
				for ($depth = $iterator->getDepth() - 1; $depth >= 0; $depth--) {
					$path = array($iterator->getSubIterator($depth)->current()->getFilename() => $path);
				}
				$contents = array_merge_recursive($contents, $path);
			}
		}

		return $contents;
	}

	protected function get_module_json($name){
		if (!isset($this->modules[$name])){ return false; }

		return $this->get_json_from_folder($name, $this->modules[$name]);
	}

	public function get_name_inputs(){
		$inputs = '';

		$inputs .= '<label>What should we call this Field Group?<input type="text" name="acfGroupName" value="'.$this->acfGroupName.'" placeholder="Naming things is hard."/></label>';
		$inputs .= '<label>And how about your Flexible Content field?<input type="text" name="acfModulesName" value="'.$this->acfModulesName.'" placeholder="Well, aren\'t you particular?"/></label>';

		return $inputs;
	}

	public function get_modules_as_checkboxes(){
		$output = '';

		if (!$this->modules){
			return '<h3>Sorry, this restaurant is closed for a health inspection.</h3>';
		}

		foreach ($this->modules as $name => $files){
			// TODO: screenshots?

			$checked = '';
			if (in_array($name, $this->defaultModules)){ $checked = ' checked'; }

			$output .= '<label><input type="checkbox" name="acfLayouts['.base64_encode($name).']"'.$checked.'/> '.$name.'</label>';
		}

		return '<h3>Pick as many as you like:</h3><section class="fieldset horiz x3 no-grow">'.$output.'</section>'; // actual fieldsets can't flex in Chrome, ugh
	}

	public function get_notices(){
		$_notices = '';
		foreach ($this->notices as $type => $notices){
			$_notices .= '<aside class="notification '.$type.'"><div class="content-width"><p>'.implode('</p><p>', $notices).'</p></div></aside>';
		}
		return $_notices;
	}
}

function slugify($text, $underscore = false){
	// do some hand-holding
	$replacements = array(
		' ' => '-',
		'&' => 'and',
		'+' => 'and',
	);
	$text = str_replace(array_keys($replacements), array_values($replacements), $text);

	// collapse non-letters
	$text = preg_replace('/[^a-zA-Z0-9\-]+/', '', $text);

	// trim orphaned dashes
	$text = trim($text, '-');

	// collapse multiple dashes to just one
	$text = preg_replace('/-+/', '-', $text);

	if ($underscore){
		$text = str_replace('-', '_', $text);
	}

	// lowercase
	return strtolower($text);
}

function zen_debug(){
	$debuggables = func_get_args();
	foreach ($debuggables as $foo){
		echo '<pre class="debug">'; var_dump($foo); echo '</pre>';
	}
}

?>
