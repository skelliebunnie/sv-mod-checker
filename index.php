<?php require_once 'functions.php'; ?>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" type="image/png" href="./favicon.png">
	<title>STARDEW VALLEY MOD DEPENDENCY CHECKER</title>
	<link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
	<link href="https://unpkg.com/sanitize.css/typography.css" rel="stylesheet"/>
	<link href="https://unpkg.com/sanitize.css/forms.css" rel="stylesheet"/>
	<script src="https://kit.fontawesome.com/adc1a4d4e8.js" crossorigin="anonymous"></script>
	<link href="style.css" rel="stylesheet"/>
</head>
<!-- 
https://stackoverflow.com/questions/32296838/filter-directories-from-recursivedirectoryiterator
https://www.codewall.co.uk/how-to-read-json-file-using-php-examples/
https://www.digitalocean.com/community/tutorials/css-collapsible
https://www.phptutorial.net/php-tutorial/php-array-destructuring/
-->
	<?php
		// set up default path to mods folder -- this is what we'll be searching through for manifest.json files
		$defaultPath = "C:\Program Files (x86)\Steam\steamapps\common\Stardew Valley\Mods";
		$folderPath = "";
		$continue = false;
		$noModsMessage = array(
			"title" => "",
			"message" => ""
		);
		
		if(!$_POST || ($_POST && array_key_exists('useDefaultPath', $_POST) && $_POST['useDefaultPath'] === "true")) {
			$folderPath = $defaultPath;

		} else if(array_key_exists('rootDirectory', $_POST) && $_POST['rootDirectory'] !== "") {
			$folderPath = $_POST['rootDirectory'];

		}

		// make sure the path actually exists AND it's a directory!
		if(realpath($folderPath) && is_dir($folderPath)) {
			$continue = true;

		} else {
			$noModsMessage["title"] = "Path is not a directory!";
			$noModsMessage["message"] = "Please provide the path to your Mods directory, not to a specific file.";
		}

		/**
		 * $mods @var array; for all successfully json decoded mods
		 * $modIDs @var array; UniqueID => baseName
		 * $skipped @var array; for mods that weren't json decoded successfully
		 */
		$mods = array(); $modIDs = array(); $skipped = array(); $missingentries = array();
		if($continue) {
			// set up that recursive directory iterator!
			$dir = new RecursiveDirectoryIterator($folderPath);

			//define the directories you don't want to include
			$excludeDirs = array("__vortex_staging_folder", "Mods");

			// here we use a Recursive Callback Filter Iterator to:
			// recursively iterate over the directories in the root path
			// and apply a filter - via a callback function - against the results
			$files = new RecursiveCallbackFilterIterator($dir, function($file, $key, $iterator) use ($excludeDirs) {
				// basically: dir.filter(file => file.hasChildren() && !$dir.includes(file.fileName))
				if($iterator->hasChildren() && !in_array($file->getFilename(), $excludeDirs)) {
		        return true;
		    }
		    // if we haven't returned above, check to see if the iterator is NOT a directory
		    // AND the filename is 'manifest.json' (we only need data from this file)
		    if(!$iterator->hasChildren() && $file->getFilename() == "manifest.json") {
		    	return $file->isFile();
		    }
			});

			// ignore the following mods
			$ignore = array("YourName.YourOthersPacksAndMods");

			foreach(new RecursiveIteratorIterator($files) as $file) {
				$data = get_data($file); // functions.php - decode json and return with a few other data keys
			   
			  if($data !== NULL && !in_array_i($data["UniqueID"], $ignore)) {
			  	$mod = create_mod_data($data, $ignore);
			  	$modID = $mod["id"];
			  	// this is the parent folder (e.g. Stardew Valley Expanded)
			  	// mods can include multiple "child mods" with their own manifests
			  	// Since you only need to download the main mod, that's how these will be grouped
			  	$baseName = $mod["base"];
			  	// add the mod's UniqueID and baseName to modIDs for future use
			  	$modIDs[strtolower($modID)] = array("base" => $baseName, "id" => $modID);
			  	
			  	// since there might be multiple "children" with different manifests
			  	// and therefore different UniqueIDs, we're pushing all those ids into an array
			  	// in the "base" data
			  	if(!array_key_exists($baseName, $mods)) {
			  		$mods[$baseName] = array(
			  			"name" => $baseName,
			  			"ids" => array(),
			  			"dependsOn" => array(),
			  			"requiredBy" => array(),
			  			"children" => array()
			  		);
			  	}
			  	// each child "records" it's own dependencies, but we also want to merge
			  	// all the dependencies into the base "dependsOn" array since ultimately
			  	// the entire mod will require all the dependencies from all the children
			  	// $mods[$baseName]["dependsOn"] = array_merge($mods[$baseName]["dependsOn"], $mod["dependsOn"]);
			  	$mods[$baseName]["children"][$modID] = $mod;

			  	// add the "child" mod's UniqueID to the "base" list of ids
			  	array_push($mods[$baseName]["ids"], $modID);

			  } else {
			  	// if the data returned from get_data() is NULL, it's a "bad mod" (couldn't be read)
			  	// the JSON file probably just couldn't be parsed/decoded, but either way it needs to be checked
			  	array_push($skipped, array("name" => $modName, "path" => $path));

			  }
			}

			// now that we've seen all of the mods, we loop through all the uniqueIDs we have
			// $modIDs: array("id" => ["id" => id, "base" => basename])
			foreach(array_keys($modIDs) as $id) {
				$base = $modIDs[$id]["base"]; // get the base name of the mod
				$modID = $modIDs[$id]["id"]; // id for specific mod
				$mod = $mods[$base]["children"][$modID]; // actual specific mod

				$dependsOn = $mod["dependsOn"]; // dependencies for individual, specific mod

				foreach($dependsOn as $dependency) {
					$depID = array_key_exists("id",$dependency) ? $dependency["id"] : null;
					$depBase = ($depID !== null && in_array_i($depID, array_keys($modIDs))) ? $modIDs[strtolower($depID)]["base"] : null;
					$dep = fix_bad_dep($dependency, $depID);
					$installed = $depBase !== null ? true : false;

					if($dep !== null) {
						// update the dependency in the "dependsOn" array for the mod
						// AND the assoc. array we're working with now
						$mods[$base][$modID]["dependsOn"][$depID]["installed"] = $installed;
						$dep["installed"] = $installed;

						if($installed) {
							$mods[$base][$modID]["dependsOn"][$depID]["base"] = $depBase;
							$dep["base"] = $depBase;
						}

						// if the dependency is required AND installed AND the current mod is not listed
						// (by *base* name) in the "requiredBy" array of the dependency mod, add it!
						if($dep["required"] && $installed && !array_key_exists($base, array_keys($mods[$depBase]["requiredBy"]))) {
							$mods[$depBase]["requiredBy"][$base] = $mod;
						}

						// if the base name of the dependency is NOT in the list of array keys in the top-level mod "dependsOn" array
						// we can just add the dependency as-is
						if(!in_array_i($depBase, array_keys($mod["dependsOn"]))) {
							$mods[$base]["dependsOn"][$depBase] = $dep;

						} else if(in_array_i($depBase, array_keys($mod["dependsOn"])) && $dep["required"]) {
							// if the dependency as already been added, let's check to make sure the "required" state is true
							// if any of the "child" mods do require it
							$mods[$base]["dependsOn"][$depBase]["required"] = true;
						}
					}
				}
			}

			foreach($mods as $mod) {
				$name = $mod["name"];
				$dependsOn = $mod["dependsOn"];
				usort($dependsOn, "sort_dependencies");
				$mods[$name]["dependsOn"] = array_unique($dependsOn, SORT_REGULAR);
			}
		}

		if(count($mods) === 0) {
			$noModsMessage["title"] = "No mods found!";
			$noModsMessage["message"] = "No manifest.json files were found in the directory provided or in any child directories. Please try another path.";
		}

	?>
	<h1 class="site-title">STARDEW VALLEY MOD DEPENDENCY CHECKER</h1>


	<form action="index.php" method="post">
		<button type="submit">Get Mods</button>
		<label for="useDefaultPath">Use Default Path: <input type="checkbox" id="useDefaultPath" name="useDefaultPath" value="true" <?php if($folderPath === $defaultPath) { echo "checked"; } ?>><i class="<?php if($folderPath === $defaultPath) { echo "fa-solid fa-square-check"; } else { echo "fa-regular fa-square"; } ?> fa-xl"></i></label>
		<label for="rootDirectory" class="hide-on-checked <?php if($folderPath === $defaultPath) { echo "hide"; } ?>">Directory: <input type="text" id="rootDirectory" name="rootDirectory" value="<?php echo $folderPath; ?>"></label>
	</form>
	<?php echo "<small>Current path: ${folderPath}</small>"; ?>

	<div class="messages">
		<div id="error" class="msg-box error <?php if(count($mods) > 0) { echo 'hidden'; } ?>">
			<?php if(count($mods) === 0) { ?>
				<h2><?php echo $noModsMessage["title"]; ?></h2>
				<p> <?php echo $noModsMessage["message"]; ?> </p>
			<?php } else { ?>
				<h2>REQUIRED MODS MISSING!</h2>
				<p class="list"></p>
			<?php } ?>
		</div>

		<div id="warning" class="msg-box warning hidden">
			<h2>MODS SKIPPED!</h2>
			<p class="list"></p>
		</div>

		<div id="alert" class="msg-box alert hidden">
			<h2>MISSING ENTRIES!</h2>
			<p class="list"></p>
		</div>
	</div>

	<?php if(count($mods) > 0) { ?>	
	<!-- all mods -->
	<div id="allMods">
		<h1>All Mods (<?php echo count($mods); ?>)</h1>
	
		<div class="key">
			<div class="color red"><strong>(!) Required, not installed</strong></div>
			<div class="color green">Required, installed</div>
			<div class="color blue">Not required, installed (optional)</div>
			<div class="color opt">Not required, not installed</div>
		</div>
		
		<article class="modlist masonry">
				<?php 
				foreach($mods as $mod) {
				?>
					<section class="mod" data-hasdependencies="<?php $hasdeps = count($mod["dependsOn"]) > 0 ? "true" : "false"; echo $hasdeps; ?>">
						<h4 class="title"><?php echo $mod["name"]; ?></h4>
						<?php 
						if(count($mod["dependsOn"]) > 0) {
							?>
							<ul class="dependencies">
								<?php 
								foreach($mod["dependsOn"] as $dep) {
									$depID = $dep["id"];
									$depIDLower = strtolower($depID);
									$depName = array_key_exists($depIDLower, $modIDs) ? $modIDs[$depIDLower]["base"] : null;

									$req = $dep["required"] === true ? "true" : "false";
									$installed = $dep["installed"] === true ? "true" : "false";

									if($depName !== null && $depName !== $mod["name"]) {
										echo "<li data-required='${req}' data-installed='${installed}'>" . $depName . "</li>";

									} else if($depName == null && $depName !== $mod["name"]) {
										echo "<li data-required='${req}' data-installed='${installed}'>" . $depID . "</li>";
									}
								}
								?>
							</ul>
							<?php
						} 
						?>
					</section>
				<?php
				}
				?>
		</article>
	</div>

	<!-- mods required by other mods -->
	<div id="topLevelMods">
		<h3>Mods Required by Other Mods (<span id="reqMods"></span>)</h3>
		<article class="modlist">
			<?php
				foreach($mods as $mod) {
					if(count($mod["requiredBy"]) > 0) {
						?>
						<section class="mod expandable">
							<h4 class="title"><?php echo "${mod['name']} (" . count($mod["requiredBy"]) . ")"; ?> <i class="fa-solid fa-angle-down"></i></h4>
							<div class="content">
								<div class="inner">
									<ul>
										<?php 
										foreach($mod["requiredBy"] as $dep) {
											echo "<li>" . $dep["name"] . "</li>";
										} 
										?>
									</ul>
								</div>
							</div>
						</section>
						<?php
					}
				}
			?>
		</article>
	</div>

	<?php } ?>

	<script type="text/javascript">
		let rootDirectoryLabel = document.querySelector("[for='rootDirectory']");
		let useDefaultPathLabel = document.querySelector("[for='useDefaultPath']");
		let icon = useDefaultPathLabel.querySelector("i");

		useDefaultPathLabel.addEventListener("click", function() {
			let input = useDefaultPathLabel.querySelector("input");
			if(useDefaultPathLabel.querySelector("input").checked) {
				icon.classList.remove("fa-regular", "fa-square");
				icon.classList.add("fa-solid", "fa-square-check");

				rootDirectoryLabel.classList.add("hide");

			} else {
				icon.classList.add("fa-regular", "fa-square");
				icon.classList.remove("fa-solid", "fa-square-check");

				rootDirectoryLabel.classList.remove("hide");
			}
		});
	
		const mods = <?php echo json_encode($mods); ?>;
		
		if(Object.keys(mods).length > 0) {
			const reqMods = Object.keys(mods).filter(key => Object.keys(mods[key].requiredBy).length > 0);
			const otherMods = Object.keys(mods).filter(key => Object.keys(mods[key].requiredBy).length === 0);

			let errorMsg = document.querySelector("#error");
			let warningMsg = document.querySelector("#warning");

			document.querySelector("#reqMods").innerText = reqMods.length;

			let sections = document.querySelectorAll(".expandable");
			sections.forEach(ex => {
				let title = ex.querySelector(".title");
				let content = ex.querySelector(".content");

				title.addEventListener("click", function() {
					sections.forEach(section => { 
						if(section.querySelector(".title").innerText !== title.innerText) {
							section.classList.remove("expanded"); 
						}
					});

					ex.classList.toggle("expanded");
				});
			});

			let missingRequiredMods = [];
			Object.keys(mods).forEach(key => {
				let mod = mods[key];
				let dependencies = mod.dependsOn;
				if(dependencies.length > 0) {
					dependencies.forEach(dep => {
						if(dep.required && !dep.installed && !missingRequiredMods.includes(dep.id)) missingRequiredMods.push(dep.id);
					});
				}
			});
			if(missingRequiredMods.length > 0) {
				errorMsg.querySelector(".list").innerHTML = missingRequiredMods.join(", ");
				errorMsg.classList.remove("hidden");
			}

			let skippedMods = <?php echo json_encode($skipped); ?>;
			if(skippedMods.length > 0) {
				warningMsg.querySelector(".list").innerHTML = skippedMods.join(", ");
				warningMsg.classList.remove("hidden");
			}

			let missingEntries = <?php echo json_encode($missingentries); ?>;
			if(skippedMods.length > 0) {
				alertMsg.querySelector(".list").innerHTML = missingEntries.join(", ");
				alertMsg.classList.remove("hidden");
			}
		}
	</script>
</body>
</html>