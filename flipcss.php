<?php
/* 
 * flipcss.php 0.8
 * 
 * Link:
 *  https://github.com/daif/flipcss.git
 * 
 * Description:
 *  This tool flip CSS rules direction from LTR to RTL or vice versa .
 *  tool support these CSS rules: float, text-align, padding, margin
 *  and border-radius .
 * 
 * 
*/

if (in_array('--help', $_SERVER['argv']) || empty($_SERVER['argv'])) {
    echo <<<EOF
CLI tool to flip CSS rules direction from LTR to RTL or vice versa.

  Usage:
  php $argv[0] [OPTIONS] <orignal_file>

  --help        Print this page.

  --dir         Specify the direction

  --esc         List of escaped classes (class1,class2,...)

EOF;
  exit;
}

array_shift($_SERVER['argv']);
$direction = 'rtl';
$escaped = array('left','right');
while ($param = array_shift($_SERVER['argv'])) {
  switch ($param) {
    case '--dir':
      $direction = array_shift($_SERVER['argv']);
      break;
    case '--esc':
      $escaped = explode(',', array_shift($_SERVER['argv']));
    default:
      $file = $param;
      break;
  }
}

if (!file_exists($file)) {
    echo "\033[91mCould not open input file: ".$file."\033[0m\n";
    exit;
}

if (!in_array($direction, array('rtl', 'ltr'))) {
    echo "\033[91mCould not define input direction: ".$direction."\033[0m\n";
    exit;    
}

flipTheCSSFile($file, $direction, $escaped);

function flipTheCSSFile($css_file, $dir='rtl', $escaped=array()) {
    $css_data = file_get_contents($css_file);
    //remove comments 
    $css_data = preg_replace('/\/\*(.*)?\*\//Usi','' ,$css_data);
    //rewrite padding,margin,border
    $css_data = preg_replace('/(\h*)(padding|margin|border):(\d+.+)\h+(\d+.+)\h+(\d+.+)\h+(\d+.+)\h*;/Ui',"\\1\\2-right:\\4;\\1\\2-left:\\5;" ,$css_data);
    //rewrite border-radius 
    $css_data = preg_replace('/(\h*|)border-radius:(.+)\h+(.+)\h+(.+)\h+(.+)\h*;/Ui',"\\1border-top-left-radius:\\2;\\1border-top-".
                "right-radius:\\3;\\1border-bottom-right-radius:\\4;\\1border-bottom-left-radius:\\5;", $css_data);
    //start parsing css file
    $css_data = preg_replace('/(@media .+){(.+)}\s*}/Uis', '\1$$$\2}$$$', $css_data);
    preg_match_all('/(.+){(.+)(}\$\$\$|})/Uis', $css_data, $css_arr);
    $css_flipped    = "/* Created by flipcss.php 0.8 (https://github.com/daif/flipcss) */\n\nbody{\n\tdirection: $dir;\n}\n\n";
   foreach($css_arr[0] as $key=>$val) {
        //ignore escaped classes
        if(!preg_match('/('.implode('|', array_map('preg_quote', $escaped)).')/i', $css_arr[1][$key])) {
      if(preg_match('/left|right/i', $css_arr[2][$key])) {
        if($rules = flipTheCSSRules($css_arr[2][$key])) {
          $css_flipped .= trim(str_replace('$$$','{',$css_arr[1][$key]));
          $css_flipped .= " {\n\t".trim($rules)."\n";
          $css_flipped .= str_replace('$$$',"\n}",$css_arr[3][$key])."\n\n";
        }
      }
    }
    }
    
    $flipped_file = substr($css_file,0,-4).'-'.$dir.'.css';
    file_put_contents($flipped_file, $css_flipped);
    echo "\033[92mCSS file ".basename($css_file)." has been flipped to $dir direction.\033[0m\n";
}

function flipTheCSSRules($rules) {
    $return         = '';
    $rules_arr      = explode(";", $rules);
    foreach($rules_arr as $rule) {
        //ignore rules that doesn't need flipping
        if(preg_match('/(left|right)/i', $rule)) {
            //flip float
            if(preg_match('/float\h*:\h*(.+)/i', $rule, $rule_arr)) {
                $rule = 'float: '.((trim($rule_arr[1])=='left')?'right':'left');
                $return .="\t".trim($rule).";\n";
            
            //flip text-align
            } elseif(preg_match('/text-align\h*:\h*(.+)/i', $rule, $rule_arr)) {
                $rule = 'text-align: '.((trim($rule_arr[1])=='left')?'right':'left');
                $return .="\t".trim($rule).";\n";
            
            //flip padding, margin, border
            } elseif(preg_match('/(\*|)(margin|padding|border)-(left|right)\h*:\h*(.+)/i', $rule, $rule_arr)) {
                $dir = ((trim($rule_arr[3])=='left')?'right':'left');
                //reset direction rule
                if((trim($rule_arr[3]) == 'left' && !preg_match('/'.trim($rule_arr[2]).'\-right/i', $rules)) || (trim($rule_arr[2]) == 'right' && !preg_match('/'.trim($rule_arr[2]).'\-left/i', $rules))) {
                    $rule = trim($rule_arr[1]).trim($rule_arr[2]).'-'.$rule_arr[3].": 0;\n\t";
                } else {
                    $rule = '';
                }
                $rule .= trim($rule_arr[1]).trim($rule_arr[2]).'-'.$dir.': '.$rule_arr[4];
                $return .="\t".trim($rule).";\n";
            
            //flip border-radius
            } elseif(preg_match('/border-(top|bottom)-(left|right)-radius\h*:\h*(.+)/i', $rule, $rule_arr)) {
                $dir = ((trim($rule_arr[2])=='left')?'right':'left');
                //reset direction rule
                if((trim($rule_arr[2]) == 'left' && !preg_match('/'.trim($rule_arr[1]).'\-right/i', $rules)) || (trim($rule_arr[2]) == 'right' && !preg_match('/'.trim($rule_arr[1]).'\-left/i', $rules))) {
                    $rule = 'border-'.$rule_arr[1].'-'.$rule_arr[2].'-radius: 0;'."\n\t";
                } else {
                    $rule = '';
                }
                //write new direction rule
                $rule .= 'border-'.$rule_arr[1].'-'.$dir.'-radius: '.$rule_arr[3];
                $return .="\t".trim($rule).";\n";
            
            //flip left, right
            } elseif(preg_match('/\h+(left|right)\h*:\h*(.+)/i', $rule, $rule_arr)) {
                $dir = ((trim($rule_arr[1])=='left')?'right':'left');
                //reset LTR rule
                if((trim($rule_arr[1]) == 'left' && !preg_match('/\h+right\h*:/i', $rules)) || (trim($rule_arr[1]) == 'right' && !preg_match('/\h+left\h*:/i', $rules))) {
                    $rule = trim($rule_arr[1]).": auto;\n\t";
                } else {
                    $rule = '';
                }
                $rule .= $dir.': '.$rule_arr[2];
                $return .="\t".trim($rule).";\n";
            }
        }
    }
    return($return);
}

?>