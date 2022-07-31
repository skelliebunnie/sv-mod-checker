# Stardew Valley Mod Checker
**You will need to download this and run it locally**
Once downloaded, simply open `index.php`! If your mods are installed at the default location (on Windows, `C:\Program Files (x86)\Steam\steamapps\common\Stardew Valley\Mods`), you'll see a list of all your mods with their dependencies. Dependencies are color-coded based on whether or not they're required, and whether or not they're installed.

If your mods are installed elsewhere, simply uncheck the "Use Default Path" checkbox, enter the path (to the Mods folder, not to a specific mod!) in the input field, and click "Get Mods".

Where possible, the "main" mod name will be displayed (e.g. "Stardew Valley Expanded") to avoid duplication (SVE has several "child mods" packaged together, such as "[CP] Stardew Valley Expanded"). If a "child" mod is required, the entire mod package is required. This "main" name can only be retrieved if the mod is installed, however. For mods that are not installed, the `UniqueID` is displayed, as found in the "Dependencies" array of the requiring mod's manifest file. Most UniqueIDs are in the format `<username>.<mod(short)name>` so they should be easy to find.

![Mod Checker Screenshot](/screenshot.png?raw=true "Screenshot")