<?php

//Adds content when viewing a level
function pygame_node_level_view($node, $view_mode, $langcode){
	if($view_mode == 'full'){
		$node->content['pygame_leveldisplay']=array(
			'#theme' => 'pygame_level_display_initial',
			'node' => $node
		);
		$state = array('node_id' => $node->nid);
		$node->content['pygame_codeform']=drupal_build_form('pygame_node_level_codeform', $state);
	}
}

//Initial view of level
function pygame_level_display_initial($variables){
	$level = pygame_level_get($variables['node']);
	return pygame_level_render($level);
}

//Initial level object
function pygame_level_get($node){
	$dict = array(
		'code'=>$node->pygame_node_level_code_generate,
		'data'=>array('map'=>array(),'players'=>array())
	);
	$level = pygame_run_script_with_input($dict);
	return $level;
}

//Returns the form for entering code
function pygame_node_level_codeform($form, &$form_state) {
  //$form = array();
  $form['sourcecode'] = array(
    '#type' => 'textarea'
	);
  $form['submitbutton'] = array(
	'#type' => 'submit',
	'#value' => 'Run Code',
	'#ajax' => array(
		'callback' => 'pygame_node_level_form_run_callback',
     )
	);
  $form['node_id'] = array(
	'#type'=>'hidden',
	'#value'=>$form_state['node_id'],
	);
  $form['#ajax'] = array(
      'callback' => 'pygame_node_level_form_run_callback',
     );
	$form['#attached']['js'][] = drupal_get_path('module','pygame').'/js/pygame.js';
	$form['#attached']['css'][] = drupal_get_path('module','pygame').'/css/pygame.css';
  return $form;
}

#Renders a level dictionary
function pygame_level_render($level){
	$ret = "<div class='csstable'>";
	for($y=0;$y<count($level['map']);$y++){
		$ret .= "<div class='csstr'>";
		for($x=0;$x<count($level['map'][$y];$x++){
			$ret .= "<div class='csstd'>";
			$cell = $level['map'][$y][$x];
			$tilenode = node_load($cell);
			$img = $tilenode->pygame_node_tile_image['und'][0]['uri'];
			$uri = file_create_url($img);
			$ret .= '<img src="'.$uri.'" />';
			/*if($this->player_x == $x && $this->player_y == $y){
				$ret .= "<div class='csstd' style='background-image:url(/sites/all/themes/pygame_theme/sprite.jpg)'><br/></div>";
//				$ret .= '<img src="/sites/all/themes/pygame_theme/sprite.jpg" class="arenatile" />';
			}else{
				$ret .= "<div class='csstd' style='background-image:url(/sites/all/themes/pygame_theme/background.jpg)'><br/></div>";
				//$ret .= '<img src="/sites/all/themes/pygame_theme/background.jpg" class="arenatile" />';
			}*/
			$ret .= $cell;
			$ret .= "</div>";
		}
		$ret .= "</div>";
	}
	$ret .= "</div>";
	return $ret;	
}

/*
class AIArena{
	public $height = 20;
	public $width = 20;
	public $player_x = 10;
	public $player_y = 10;
	public function to_script(){
		return "";
	}
	public function render(){
	
	
		$ret = "<div class='csstable'>";
		for($y=0;$y<$this->height;$y++){
			$ret .= "<div class='csstr'>";
			for($x=0;$x<$this->width;$x++){
				//$ret .= "<div class='csstd'>";
				if($this->player_x == $x && $this->player_y == $y){
					$ret .= "<div class='csstd' style='background-image:url(/sites/all/themes/pygame_theme/sprite.jpg)'><br/></div>";
//					$ret .= '<img src="/sites/all/themes/pygame_theme/sprite.jpg" class="arenatile" />';
				}else{
					$ret .= "<div class='csstd' style='background-image:url(/sites/all/themes/pygame_theme/background.jpg)'><br/></div>";
					//$ret .= '<img src="/sites/all/themes/pygame_theme/background.jpg" class="arenatile" />';
				}
				//$ret .= "</div>";
			}
			$ret .= "</div>";
		}
		$ret .= "</div>";
		return $ret;	
	}
}
*/

//Called when user enters code and hits run
//Returns AJAX commands to animate the level
function pygame_node_level_form_run_callback($form, $form_state){
//	return "<p>test return value</p>";
//return print_r($form_state,true);

	$code = $form_state['input']['sourcecode'];
	
	$level_node_id = $form_state['input']['node_id'];
	$level_node = node_load($level_node_id);
	
	return pygame_node_level_ajax_commands($code, $level_node);
}

function pygame_node_level_ajax_commands($code, $level_node){

	$ajax_commands = array();

	$level = pygame_level_get($level_node);

	$level_view = $level->render();
	
	$steps = array();
	
	$cont=true;
	while($cont){
		//Get 1 or more commands from user code
		$input_user = array(
			'code'=>$code,
			'data'=>array(
				'level'=>$level,
				'commands'=>array()
			)
		)
		$output_user = pygame_run_script_with_input($input_user);
		
		if(count($output_user['commands'])>0){
		
			//Pass each user command to the level code
			$command_i = 0;
			while($cont && ($command_i < count($output_user['commands']))){
			
				$input_level = array(
					'code'=>$level_node->pygame_node_level_code_run,
					'data'=>array(
						'usercommand'=>$output_user[$command_i],
						'cont'=>1,
						'levelcommands'=>array(),
						'step'=>count($steps)
					)
				);
				$output_level=pygame_run_script_with_input($input_level);
				$commands = $output_level['levelcommands'];
				$fcommands = pygame_update_level($level, $commands);
				$steps[] = $fcommands;
				if($output_level['cont']==0){
					$cont=false;
				}
				$command_i ++;
			}
		}else{
			$cont = false;
		}
	}
	
	$steps_json = json_encode($steps);
	
	$ajax_commands[] = ajax_command_replace('#results-div', $level_view);
	$ajax_commands[] = ajax_command_invoke(NULL, 'pygame_steps', array($steps_json));
	return array(
		'#type' => 'ajax',
		'#commands' => $ajax_commands
	);	
	
}

//Apply each command to level
//Return all commands that were successful
function pygame_update_level(&$level, $commands){
	$ret = array();
	foreach($commands as $command){
		if($command[0]='move'){
			$level['players'][$command[1]]['x'] += $command[2];
			$level['players'][$command[1]]['y'] += $command[3];
			$ret[] = $command;
		} else if($command[0]='updatetile'){
			$level['map'][$command[1]][$command[2]] = $command[3];
			$ret[] = $command;
		}
	}
	return $ret;
}


/*

Code for running python sandbox

Encodes input as JSON
Runs "python_runner.py" python script
Decodes script output as JSON
*/
function pygame_run_script_with_input($input_dict){
	$input = json_encode($inputs);
	$ifile = file_save_data($input);
	$path = drupal_realpath(drupal_get_path('module','pygame').'/python_runner.py');
	$ipath = drupal_realpath($ifile->uri);
	$cmd = '"' . $path . '" < "'.$ipath.'"';
	$output = shell_exec($cmd);
	$output_dict = json_decode($output);
	return $output_dict;
}