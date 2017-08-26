<?php
namespace serhatozles\compressio;

/*
 * @author: Serhat ÖZLEŞ
 * @email: serhatozles@gmail.com
 */

// GLOBAL VARIABLES
define("BACKUP_FOLDER", getcwd() . DIRECTORY_SEPARATOR . 'backup');
define("COOKIE_FILE", __DIR__ . DIRECTORY_SEPARATOR . 'cookie.txt');

class CompressorIO
{

	public $backup = true;
	public $console = false;

	public function fileSave($Source, $fildeDir)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$headers = [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
			'Accept-Language: tr-TR,tr;q=0.8,en-US;q=0.6,en;q=0.4	',
			'Cache-Control: no-cache',
			'Upgrade-Insecure-Requests: 1', //Your referrer address
		];

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_COOKIEJAR, \COOKIE_FILE);  //could be empty, but cause problems on some hosts
		curl_setopt($ch, CURLOPT_COOKIEFILE, \COOKIE_FILE);  //could be empty, but cause problems on some hosts
		curl_setopt($ch, CURLOPT_URL, $Source);

		$out = curl_exec($ch);

		$fp = fopen($fildeDir, 'w');
		fwrite($fp, $out);
		fclose($fp);

		curl_close($ch);

	}

	private function doIt($filename)
	{
		$delimiter = '-------------' . uniqid();
		$data = '';
		$data .= "--" . $delimiter . "\r\n";
		$data .= 'Content-Disposition: form-data; name="files[]";  filename="' . basename($filename) . '"' . "\r\n";
		$data .= 'Content-Type: ' . exif_imagetype($filename) . "\r\n\r\n";
		$data .= file_get_contents($filename);
		$data .= "\r\n" . "--" . $delimiter . "--\r\n";
		$str = $data;
// set up cURL
		$ch = curl_init('https://compressor.io/server/Lossy.php');
		curl_setopt_array($ch, [
			CURLOPT_HEADER         => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => [ // we need to send these two headers
				'Content-Type: multipart/form-data; boundary=' . $delimiter,
				'Content-Length: ' . strlen($str),
			],
			CURLOPT_POSTFIELDS     => $data,
			CURLOPT_COOKIEJAR      => \COOKIE_FILE,
			CURLOPT_COOKIEFILE     => \COOKIE_FILE,
			CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3",
		]);
		$ress = curl_exec($ch);
		curl_close($ch);

		return json_decode($ress, true);
	}

	public function rsearch($folder, $pattern)
	{
		$dir = new \RecursiveDirectoryIterator($folder);
		$ite = new \RecursiveIteratorIterator($dir);
		$files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
		$fileList = [];
		foreach ($files as $file) {
			$fileList = array_merge($fileList, $file);
		}

		return $fileList;
	}

	public function findFolder($extensions = "png|jpg|jpeg|gif")
	{

		return $this->rsearch(getcwd(), '/.*\.(?:' . $extensions . ')/');
	}

	public function compress($files)
	{

		$result = [];

		foreach ($files as $fileDir) {

			$filename = basename($fileDir);

			// without backup folder.
			if (strpos($fileDir, \BACKUP_FOLDER) !== false) {
				continue;
			}

			$compressedResult = $this->doIt($fileDir);

			if (!empty($compressedResult['files'][0]['url'])) {
				if ($compressedResult['files'][0]['size'] <= $compressedResult['files'][0]['sizeAfter']) {
					$message = "{$filename} is already compressed.\r\n";
					if ($this->console) {
						echo $message;
					} else {
						$result[] = $message;
					}
				} else {

					// backup.
					if ($this->backup) {

						$backupFolder = str_replace(getcwd(), \BACKUP_FOLDER, $fileDir);

						if (!file_exists(dirname($backupFolder)))
							mkdir(dirname($backupFolder), 0777, true);

						if (!copy($fileDir, $backupFolder)) {
							$message = "$fileDir can not copied...\n";
							if ($this->console) {
								echo $message;
							} else {
								$result[] = $message;
							}
						}
					}

					$this->fileSave($compressedResult['files'][0]['url'], $fileDir);
					$message = "{$filename} is compressed. Before Size: {$compressedResult['files'][0]['size']}B, After Size: {$compressedResult['files'][0]['sizeAfter']}B\r\n";
					if ($this->console) {
						echo $message;
					} else {
						$result[] = $message;
					}
				}
			}
		}

		return $result;
	}
}

?>
