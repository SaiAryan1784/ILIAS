<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* This is the global ILIAS test suite. It searches automatically for
* components test suites by scanning all Modules/.../test and
* Services/.../test directories for test suite files.
*
* Test suite files are identified automatically, if they are named
* "ilServices[ServiceName]Suite.php" or ilModules[ModuleName]Suite.php".
*
* @author	<alex.killing@gmx.de>
*/
class ilGlobalSuite extends PHPUnit_Framework_TestSuite
{
	/**
	 * @var	string
	 */
	const PHPUNIT_GROUP_FOR_TESTS_REQUIRING_INSTALLED_ILIAS = "needsInstalledILIAS";

	/**
	 * Check if there is an installed ILIAS to run tests on.
	 *
	 * TODO: implement me correctly!
	 * @return	bool
	 */
	public function hasInstalledILIAS() {
		$ilias_ini_path = __DIR__."/../../../ilias.ini.php";

		if(!is_file($ilias_ini_path)) {
			return false;
		}

		$ilias_ini = new ilIniFile($ilias_ini_path);
		$ilias_ini->read();
		$client_data_path = $ilias_ini->readVariable("server", "absolute_path")."/".$ilias_ini->readVariable("clients", "path");

		if(!is_dir($client_data_path)) {
			return false;
		}

		include_once($ilias_ini->readVariable("server", "absolute_path")."/Services/PHPUnit/config/cfg.phpunit.php");

		if(!isset($_GET["client_id"])) {
			return false;
		}

		$phpunit_client = $_GET["client_id"];

		if(!$phpunit_client) {
			return false;
		}

		if(!is_file($client_data_path."/".$phpunit_client."/client.ini.php")) {
			return false;
		}

		return true;
	}

	public static function suite()
	{
		$suite = new ilGlobalSuite();

		require_once('include/inc.get_pear.php');
		echo "\n";
		
		// scan Modules and Services directories
		$basedirs = array("Services", "Modules");

		foreach ($basedirs as $basedir)
		{
			// read current directory
			$dir = opendir($basedir);

			while($file = readdir($dir))
			{
				if ($file != "." && $file != ".." && is_dir($basedir."/".$file))
				{
					$suite_path =
						$basedir."/".$file."/test/il".$basedir.$file."Suite.php";
					if (is_file($suite_path))
					{
						include_once($suite_path);
						
						$name = "il".$basedir.$file."Suite";
						$s = $name::suite();
						echo "Adding Suite: ".$name."\n";
						$suite->addTest($s);
						//$suite->addTestSuite("ilSettingTest");
					}
				}
			}
		}
		echo "\n";

		if (!$suite->hasInstalledILIAS()) {
			echo "Removing tests requiring an installed ILIAS.\n";
			$ff = new PHPUnit_Runner_Filter_Factory();
			$ff->addFilter
				( new ReflectionClass("PHPUnit_Runner_Filter_Group_Exclude")
				, array(self::PHPUNIT_GROUP_FOR_TESTS_REQUIRING_INSTALLED_ILIAS)
				);
			$suite->injectFilter($ff);
		}
		else {
			echo "Found installed ILIAS, running all tests.\n";
		}

        return $suite;
    }
}
?>
