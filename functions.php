<?php
/**
	* https://stackoverflow.com/questions/8815586/convert-invalid-json-into-valid-json
	*	https://stackoverflow.com/questions/10290849/how-to-remove-multiple-utf-8-bom-sequences
	*	https://gist.github.com/1Franck/5076758 (json_clean_decode)
 */

/**
 * original from: https://gist.github.com/1Franck/5076758
 * Clean comments of json content and decode it with json_decode().
 * Work like the original php json_decode() function with the same params
 * updated to handle trailing commas and remove BOM
 *
 * @param   string  $json    The json string being decoded
 * @param   bool    $assoc   When TRUE, returned objects will be converted into associative arrays. 
 * @param   integer $depth   User specified recursion depth. (>=5.3)
 * @param   integer $options Bitmask of JSON decode options. (>=5.4)
 * @return  array/object
 */
function json_clean_decode($string, $assoc = false, $depth = 512, $options = 0) {

    // search and remove comments like /* */ and //
    $string = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $string);

    // clean out BOM & make sure string is properly utf-8 encoded
		$bom = pack('H*','EFBBBF');
    $string = preg_replace("/^$bom/", '', $string);
		$string = utf8_encode($string);

    // remove new lines, to make additional fixes easier
		$string = preg_replace("/\n*\t*\r*/", "", $string);
		// remove trailing commas
	  $string = preg_replace("/\}\,\s*\n*\t*\r*\]/", "}]", $string);
	  $string = preg_replace("/\}\,\s*\n*\t*\r*\}/", "}}", $string);
	  $string = preg_replace("/,\s*\n*\t*\r*\},\s*\n*\t*\r*\]/", "}]", $string);
	  $string = preg_replace("/,\s*\n*\t*\r*}/", "}", $string);

    if(version_compare(phpversion(), '5.4.0', '>=')) { 
        return json_decode($string, $assoc, $depth, $options);
    }
    elseif(version_compare(phpversion(), '5.3.0', '>=')) { 
        return json_decode($string, $assoc, $depth);
    }
    else {
        return json_decode($string, $assoc);
    }
}

function unique_multidim_array($array, $key="id", $key2="required", $key2Val=true) {
    $temp_array = array();
    $key_array = array();
   
    $i = 0;
    foreach($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;

        } else if(in_array($val[$key], $key_array) && array_key_exists($key2, $val) && $val[$key2] === $key2Val) {
        	$target = array_search($val, $temp_array);
        	$temp_array[$target] = $val;
        }

        $i++;
    }
    return $temp_array;
}

function pre_data($data,$var_dump=true) {
	echo "<pre>";
	if($var_dump) {
		var_dump($data);
	} else {
		echo $data;
	}
	echo "</pre><br/>";
}

function in_array_i($needle, $haystack) {
	return in_array(strtolower($needle), array_change_key_case($haystack));
}

// returns NULL if the file is not parsed successfully!!
function get_data($file) {
	$pathname = $file->getPathname(); // full path, including filename
  $parentDir = explode("\\", $pathname);
  $modName = ""; $childModName = "";

  if(str_contains($pathname, "Vortex") && array_key_exists(9, $parentDir)) {
  	// C:\Users\Red\AppData\Roaming\Vortex\stardewvalley\mods
  	$modName = $parentDir[9];
  	$childModName = (array_key_exists(10, $parentDir) && $parentDir[10] !== "manifest.json") ? $parentDir[10] : null;

  } else if(array_key_exists(7, $parentDir)) {
  	// C:\Program Files (x86)\Steam\steamapps\common\Stardew Valley\Mods
  	$modName = $parentDir[7];
  	$childModName = (array_key_exists(8, $parentDir) && $parentDir[8] !== "manifest.json") ? $parentDir[8] : null;

  }
  
  if($modName !== "") {
  	$json = file_get_contents($pathname);
	  $data = json_clean_decode($json, true);

	  $data["base"] = $modName;
	  $data["child"] = $childModName;

	  return $data;

  }
  return NULL;
}


function create_mod_data($data, $ignore=array()) {
	$mod = array(
		"id" => $data["UniqueID"],
		"base" => $data["base"],
		"name" => $data["Name"],
		"dependsOn" => array(),
		"requiredBy" => array()
	);

	if(array_key_exists("ContentPackFor", $data)) {
		$cpfID = $data["ContentPackFor"]["UniqueID"];
		if(!in_array($cpfID, $ignore)) {
			$mod["dependsOn"][$cpfID] = array("id" => $cpfID, "base" => "", "required" => true, "installed" => false);
		}
	}

	if(array_key_exists("Dependencies", $data)) {
		foreach($data["Dependencies"] as $dep) {
			if(array_key_exists("UniqueID", $dep) && !in_array($dep["UniqueID"], $ignore)) {
				$depID = $dep["UniqueID"];

				$req = ((array_key_exists("IsRequired", $dep) && $dep["IsRequired"] === true) || !array_key_exists("IsRequired", $dep)) ? true : false;

				if(!array_key_exists($depID, $mod["dependsOn"])) {
					$mod["dependsOn"][$depID] = array("id" => $depID, "base" => "", "required" => $req, "installed" => false);

				} else if(array_key_exists($depID, $mod["dependsOn"]) && $req === true) {
					$mod["dependsOn"][$depID]["required"] = true;
					$mod["dependsOn"][$depID]["base"] = "";
				}
			}
		}
	}

	return $mod;
}

function fix_bad_dep($dep, $id=null) {
	if(!array_key_exists("id", $dep) && $id === null) {
		return null;
	}

	if(!array_key_exists("id", $dep) && $id !== null) {
		$dep["id"] = $id;
	}

	if(!array_key_exists("required", $dep)) {
		$dep["required"] = false;
	}

	if(!array_key_exists("installed", $dep)) {
		$dep["installed"] = false;
	}

	return $dep;
}

function sort_dependencies(array $a, array $b) {
	return [$b['required'], $b['installed'], $b['base'], $b['id']] <=> [$a['required'], $a['installed'], $a['base'], $a['id']];
}