<?php
/**
 * 
 */



class DashboardHelpers
{



	function __construct()
	{

	}

	/**
	 * Format bytes to human readable string
	 * 
	 * @param int $bytes
	 * @param int $precision
	 * @return string
	 */
	public static function format_size($bytes, $precision = 2)
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	public static function wp_escape($string = '')
	{
		//	escape
	}

	/**
	 * Adds slashes to prevent issues with parsing content 
	 * @param string $string content 
	 * @param boolean $is_like
	 * 
	 * @return $string filtered content 
	 */
	public static function wp_addslashes($string = '', $is_like = false)
	{

		if ($is_like)
			$string = str_replace('\\', '\\\\\\\\', $string);
		else
			$string = str_replace('\\', '\\\\', $string);

		$string = str_replace('\'', '\\\'', $string);

		return $string;
	}

	/**
	 * 
	 * Replaces last occurence of given string 
	 * 
	 * @param mixed $search data to be replaced in content 
	 * @param mixed $replace data that will replace data from $search 
	 * @param string $subject content that will be filtered 
	 * 
	 * @return $subject filtered content 
	 */
	public static function str_lreplace($search, $replace, $subject)
	{
		$pos = strrpos($subject, $search);

		if ($pos !== false) {
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		}

		return $subject;
	}

	/**
	 * Merges two or more files into one 
	 * 
	 * @param array $files list of filepath of files to be merged
	 * @param string $filepath filepath of file that will be created after merge is done 
	 * @param boolean $remove_files if set to true it will remove files from $files array
	 * 
	 * @return $filepath filepath of new created file after merge process 
	 */
	public static function merge_files($files = array(), $filepath = '', $remove_files = false)
	{
		$out = fopen($filepath, "w");
		//Then cycle through the files reading and writing.

		foreach ($files as $file) {
			$in = fopen($file, "r");
			while ($line = fgets($in)) {
				//  print $file;
				fwrite($out, $line);
			}
			fclose($in);
		}

		//Then clean up
		fclose($out);
		if ($remove_files == true) {
			self::remove_files($files);
		}
		return $filepath;
	}

	/**
	 * Removes file or files
	 * 
	 * @param mixed $files array or single item to be removed 
	 * 
	 * @return void 
	 */
	public static function remove_files($files = '')
	{
		if (!empty($files) && is_array($files)) {
			foreach ($files as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

	}



	/**
	 * Archives all files and folders recursivelly in zip format 
	 * 
	 * @param string $source path to files and folders 
	 * @param string $destination path where archived file will be saved
	 * 
	 * @return boolean 
	 */
	public static function zip_all_data($source, $destination)
	{

		if (extension_loaded('zip')) {
			if (file_exists($source)) {
				$zip = new ZipArchive();
				if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
					$source = realpath($source);
					if (strstr($source, '\\')) {
						$source = str_replace('\\', '/', $source);
					}
					//echo $source;
					//exit;
					if (is_dir($source)) {
						$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
						foreach ($files as $file) {
							$file = realpath($file);
							if (strstr($file, '\\')) {
								$file = str_replace('\\', '/', $file);
							}
							if (is_dir($file)) {
								$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
							} else if (is_file($file)) {
								$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
							}
						}
					} else if (is_file($source)) {
						$zip->addFromString(basename($source), file_get_contents($source));
					}
				}
				return $zip->close();
			}
		}
		return false;
	}
	/**
	 * Archives all data from files list in one zip archive 
	 * @param mixed $files list of files to be archived
	 * @param string $destination path to zip file that will be created 
	 * @param string $sourcedir path of main directory where files are being stored 
	 * 
	 * @return boolean 
	 */
	public static function zip_data($files = '', $destination, $sourcedir = '')
	{
		if (extension_loaded('zip')) {
			if (!empty($files)) {
				$sourcedir = rtrim($sourcedir, '/');
				$zip = new ZipArchive();
				if ($zip->open($destination, ZIPARCHIVE::CREATE)) {

					foreach ($files as $file) {
						//  $file 
						$file = realpath($file);
						if (strstr($file, '\\')) {
							$file = str_replace('\\', '/', $file);
						}
						if (is_dir($file)) {
							//$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
							// echo $file . '<br/>';
							$zip->addEmptyDir(str_replace($sourcedir . '/', '', $file . '/'));
						} else if (is_file($file)) {



							//$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
							$zip->addFromString(str_replace($sourcedir . '/', '', $file), file_get_contents($file));
						}
					}

				}
				return $zip->close();
				exit;
			}

		}
		return false;
	}

	/**
	 * Converts file contents into array 
	 * 
	 * @param string $filename path to file 
	 * 
	 * @return array|boolean if file exists its content it will be converted into array. if not, false will be returned 
	 */
	public static function file_array($filename)
	{
		if (empty($filename)) {
			return false;
		}
		if (file_exists($filename)) {
			return file($filename);
		}

		return false;

	}

	/**
	 * Unpacks zip archive 
	 * 
	 * @param string $filename path to zip file
	 * @param string $destination destination path where file will be unpacked 
	 * @param boolean $deletezip if set to true it will delete zip file 
	 * 
	 * @return void 
	 */
	public static function unzip_data($filename = '', $destination = '', $deletezip = false)
	{
		if (!file_exists($filename))
			return;
		$zip = new ZipArchive;
		$res = $zip->open($filename);
		if ($res === TRUE) {
			if ($destination == '') {
				$destination = dirname($filename);
			}
			// echo $destination;
			$zip->extractTo($destination);
			$zip->close();
			if ($deletezip == true) {
				unlink($filename);
			}

		}
		return;
	}


	/**
	 * 
	 * Forces download of file 
	 * 
	 * @param string $filename name of file to be downloaded
	 * @param string $filepath path to file to be downloaded
	 * 
	 * @return void 
	 */
	public static function download_file($filename, $filepath)
	{
		// SECURITY FIX: Validate file path to prevent directory traversal
		if (!SecureFileOperations::validate_file_path($filepath, dirname($filepath))) {
			throw new InvalidArgumentException('Invalid file path');
		}

		// SECURITY FIX: Ensure file exists and is readable
		if (!file_exists($filepath) || !is_readable($filepath)) {
			throw new RuntimeException('File not found or not readable');
		}

		// SECURITY FIX: Sanitize filename for header
		$filename = basename($filename);
		$filename = str_replace(array("\0", "\r", "\n"), '', $filename);

		header('Content-type: "application/octet-stream"');
		header('Content-Disposition: attachment; filename="' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '"');
		header('Content-Length: ' . filesize($filepath));
		readfile($filepath);
		exit;
	}

	/**
	 * Renders html table 
	 * 
	 * @param array $content content for table body to be rendered 
	 * @param array $headers headers for table to be rendered 
	 * @param array $options various options to be used for rendering table 
	 * 
	 * @return $output formatted html table 
	 */
	public static function html_table($content = '', $headers = '', $options = array('class' => 'table'))
	{
		$output = '';
		if (!empty($headers)) {
			$output .= self::html_table_headers($headers);
		}
		$output .= '<tbody>';
		foreach ($content as $columns) {
			$output .= self::html_table_row(self::html_table_columns($columns));
		}
		$output = '<table>' . $output . '</table>';


		return $output;

	}

	/**
	 * 
	 * Counts number of lines in file content 
	 * 
	 * @param string $filename path to file 
	 * @param string $search term that is searched through file content. If exists, all lines that do not contain this term will be skipped from counting
	 * @param integer $limit number of lines to be counted in. If it is over $limit, script will stop execution and return number of files 
	 * 
	 * @return $i number of lines 
	 */
	public static function number_lines_file($filename = '', $search = '', $limit = 500)
	{

		if (empty($filename)) {
			return false;
		}

		/* $file = new SplFileObject( $filename );
		 $i = 0;
		 while (!$file->eof()) {
			 $i++;
			 $file->next();
		 }*/

		$i = 0;
		$handle = fopen($filename, "r");

		while (!feof($handle)) {
			$line = fgets($handle);
			if ((!empty($search) && stristr($line, $search)) || empty($search)) {
				$i++;
			}
		}

		fclose($handle);
		return $i;
	}

	/**
	 * Renders numeric pagination  
	 * 
	 * @param integer $record number of record from result 
	 * @param integer $limit limit of results to be shown on page 
	 * @param integer $pages_limit limit of number of pages to be shown in pagination 
	 * @param string $url url that will be appended to link in page links
	 * 
	 * @return $output rendered numeric pagination 
	 */
	public static function paginate($record = 0, $limit = 20, $pages_limit = 10, $url = '')
	{

		if ($record == 0) {
			return;
		}
		//echo $record;
		//exit;
		$current_page = filter_input(INPUT_GET, 'page');
		$get_limit = filter_input(INPUT_GET, 'limit');
		$get_lines = filter_input(INPUT_GET, 'lines');
		if (!empty($get_limit)) {
			$limit = $get_limit;
		}
		if (!empty($get_lines)) {
			$limit = $get_lines;
		}
		if (empty($current_page)) {
			$current_page = 1;
		}
		$pages = round($record / $limit);


		$query_array = self::get_query_string(true, array('page'));
		if (empty($url)) {
			$url = self::get_url();
		}
		$output = '';
		$output .= '<ul class="pagination">';
		//get current url 
		//get query string 
		if ($current_page == 1) {
			$output .= '<li class="arrow unavailable"><a href="#">&laquo;&laquo;</a></li>';
			$output .= '<li class="arrow unavailable"><a href="#">&laquo;</a></li>';
		} else {
			$query_array['page'] = 1;
			$output .= '<li class="arrow"><a href="' . self::build_url($url, $query_array) . '">&laquo;&laquo;</a></li>';
			$query_array['page'] = ($current_page - 1);
			$output .= '<li class="arrow"><a href="' . self::build_url($url, $query_array) . '" title="previous">&laquo;</a></li>';
		}
		$page_start = 1;
		//$sections = $pages/$pages_limit;
		//if($current_page == $pages_limit){

		//}
		//for( $page = 1; $page <= $pages_limit; $page++){
		//for($i = $page + 1; $i <= min($page + 11, $total_pages); $i++)
		$min_page = round($pages_limit / 2);
		if (($current_page - $min_page) < 1) {
			$start_page = 1;
		} else {
			$start_page = $current_page - $min_page;
		}
		for ($page = $start_page; $page <= min($start_page + $pages_limit, $pages); $page++) {
			if ($current_page == $page) {
				$output .= '<li class="current"><a href="#">' . $page . '</a></li>';
			} else {
				$query_array['page'] = $page;

				$output .= '<li><a href="' . self::build_url($url, $query_array) . '">' . $page . '</a></li>';
			}
		}
		if ($current_page == $pages) {
			$output .= '<li class="arrow unavailable"><a href="#">&raquo;</a></li>';
			$output .= '<li class="arrow unavailable"><a href="#">&raquo;&raquo;</a></li>';
		} else {
			$query_array['page'] = ($current_page + 1);
			$output .= '<li class="arrow"><a href="' . self::build_url($url, $query_array) . '" title="next">&raquo;</a></li>';
			$query_array['page'] = $pages;
			$output .= '<li class="arrow"><a href="' . self::build_url($url, $query_array) . '">&raquo;&raquo;</a></li>';
		}
		$output .= '</ul>';

		return $output;
	}

	/**
	 * Returns full curent url without query string 
	 * 
	 * @return string url without query string 
	 */
	public static function get_url()
	{
		return $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
	}

	/**
	 * Returns filtered query string from current url 
	 * 
	 * @param boolean $array if set to true it will return query string as array 
	 * @param string $exclude string to be excluded in query string 
	 * 
	 * @return $query filtered query string 
	 */
	public static function get_query_string($array = false, $exclude = '')
	{
		$query_string = $_SERVER['QUERY_STRING'];
		if ($array == false) {
			return $query_string;
		}
		parse_str($_SERVER['QUERY_STRING'], $query);
		if (empty($exclude) && !is_array($exclude)) {
			return $query;
		} else {
			$new_query = array();
			foreach ($query as $key => $value) {
				if (!in_array($key, $exclude)) {
					$new_query[$key] = $value;
				}
			}

			return $query;
		}

	}
	/**
	 * Builds url along with query string included 
	 * 
	 * @param string $url url to be appended
	 * @param array $query_array query string  in array format to be appended 
	 * 
	 * @return string rebuilt url 
	 */
	public static function build_url($url = '', $query_array = '')
	{

		if (!empty($query_array)) {
			$query_string = self::build_query_string($query_array);
		}
		if (empty($url)) {
			$url = self::get_url();
		}

		if (isset($query_string) && !empty($query_string)) {
			$url .= '?' . $query_string;
		}

		return '//' . $url;
	}

	/**
	 * Builds query string from array data 
	 * 
	 * @param array $query_array list of query string data 
	 * 
	 * @return string query string for url 
	 */
	public static function build_query_string($query_array)
	{
		return http_build_query($query_array);
	}

	/**
	 * 
	 * Prepares html table headers to be rendered 
	 * @param array $headers list of headers to be rendered 
	 * 
	 * @return $output prepared html table headers 
	 */
	public static function html_table_headers($headers = '')
	{
		if (empty($headers))
			return;

		$output = '<thead><tr>';
		foreach ($headers as $header) {
			$output .= '<th>' . $header . '</th>';
		}

		$output .= '</tr></thead>';
		return $output;
	}

	/**
	 * Prepares html table rows to be rendered 
	 * 
	 * @param string $content content to be put within <tr></tr> tags 
	 * 
	 * @return string content wrapped with <tr></tr> tags 
	 */
	public static function html_table_row($content = '')
	{
		return '<tr>' . $content . '</tr>';
	}

	/**
	 * Prepares html table columns to be rendered 
	 * 
	 * @param  array $columns list of columns to be wrapped with <td></td> tags 
	 * 
	 * @return $output content wrapped with <td></td> tags 
	 */
	public static function html_table_columns($columns = '')
	{
		$output = '';
		foreach ($columns as $column) {
			$output .= '<td>' . $column . '</td>';
		}

		return $output;
	}

	/**
	 * Wrapper for  form_item method 
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return $item 
	 */
	public static function form_item_render($item)
	{
		$item = self::form_item($item);
	}


	/**
	 * Prepares html form item to be rendered 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return $item string that holds form item to be rendered in template   
	 */
	public static function form_item($item)
	{
		if (empty($item) || !is_array($item))
			return;

		$item['input_id'] = self::css_id($item['name']);
		if (!isset($item['label_place'])) {
			$item['label_place'] = 'inline';
		}
		switch ($item['input_type']) {
			case 'text':
				$item = self::form_text($item);
				break;
			case 'bigtext':
				$item = self::form_big_text($item);
				break;
			case 'checkbox':
				$item = self::form_checkbox($item);
				break;
			case 'switch':
				$item = self::form_switch($item);
				break;
			case 'select':
				$item = self::form_select($item);
				break;
			case 'radio':
				$item = self::form_radio($item);
				break;
			case 'markup':
				$item = self::form_markup($item);
				break;


		}

		if (isset($item['input_render'])) {
			$output = '';
			$output .= '<div class="row">';
			if ($item['label_place'] == 'inline') {
				$output .= '<div class="columns small-6 item">';
			} else {
				$output .= '<div class="columns small-12 item">';
			}
			$output .= $item['label_render'];
			if ($item['label_place'] == 'inline') {
				$output .= '</div>';
			}
			if ($item['label_place'] == 'inline') {
				$output .= '<div class="columns small-6 item">';
			}
			$output .= $item['input_render'];

			$output .= '</div>';
			$output .= '</div>';
			if (isset($item['description'])) {
				$output .= '<div class="item-description row"><div class="columns small-12">' . $item['description'] . '</div></div>';
			}

			return $output;

		}
	}
	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_select($item = '')
	{


	}
	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_text($item = '')
	{
		$item['label_render'] = '<span for="' . $item['input_id'] . '" data-tooltip aria-haspopup="true" class="has-tip" title="' . $item['description'] . '">' . $item['input_label'] . '</span>';

		if (!isset($item['value']) || empty($item['value'])) {
			$value = $item['default_value'];
		} else {
			$value = $item['value'];
		}
		if (!isset($item['placeholder']) || empty($item['placeholder'])) {
			$placeholder = '';
		} else {
			$placeholder = $item['placeholder'];
		}
		$item['input_render'] = '<input type="text" name="' . $item['input_key'] . '" id="' . $item['input_id'] . '" value="' . $value . '" placeholder="' . $placeholder . '"/>';

		return $item;
	}

	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_big_text($item = '')
	{

	}
	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_switch($item = '')
	{
		if (isset($item['value']) && $item['value'] == '1') {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}

		$item['label_render'] = '<span data-tooltip aria-haspopup="true" class="has-tip" title="' . $item['description'] . '">' . $item['input_label'] . '</span>';

		$item['input_render'] = '<div class="switch medium round"><input id="' . $item['input_id'] . '" type="checkbox" value="on" name="' . $item['input_key'] . '" ' . $checked . '><label for="' . $item['input_id'] . '"></label></div>';


		return $item;
	}
	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_checkbox($item = '')
	{

	}
	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_radio($item = '')
	{

	}

	/**
	 * 
	 * @param array $item set of data for given $item to be prepared for rendering
	 * 
	 * @return
	 */
	public static function form_markup($item = '')
	{

	}


	/**
	 * Filters string to be prepared to set it as css id 
	 * 
	 * @param string $string string to be set as css id
	 * 
	 * @return  $string filtered css id 
	 * 
	 */
	public static function css_id($string = '')
	{
		if (empty($string)) {
			return;
		}
		$string = strtolower($string);

		$string = preg_replace("/[^a-z0-9_\s-]/", "", $string);

		$string = preg_replace("/[\s-]+/", " ", $string);

		$string = preg_replace("/[\s_]/", "-", $string);

		return $string;
	}

	/**
	 * Removes php comments from $source content 
	 * 
	 * @param string $source content from which comments will be removed 
	 * 
	 * @return $source filtered content without php comments 
	 */
	public static function strip_comments($source)
	{


		$tokens = token_get_all($source);

		//  echo '<pre>'.print_r($tokens ,true).'</pre>';
		//   echo 'tcomment ' . T_COMMENT . '<br/> ';
		$ret = "";
		foreach ($tokens as $token) {
			if (is_string($token)) {
				$ret .= $token;
			} else {
				list($id, $text) = $token;
				// echo 'id' . $id . '<br/> ';
				switch ($id) {
					case T_COMMENT:
					case T_ML_COMMENT: // we've defined this
					case T_DOC_COMMENT: // and this
						break;

					default:
						$ret .= $text;
						break;
				}
			}
		}
		// echo 'source ' . $ret .  '<br/>';
		return trim(str_replace(array('<?', '?>'), array('', ''), $ret));
	}
	/**
	 * 
	 * @param string $func function that needs to be checked if exists or it is callable 
	 * 
	 * @return bool true|false
	 */
	public static function is_function_enabled($func = '')
	{
		if (empty($func))
			return false;

		return is_callable($func) && false === stripos(ini_get('disable_functions'), $func);
	}

	/**
	 * Compares 2 files if identical. For comparison is being used md5_file() php function 
	 * @see http://www.php.net/manual/en/function.md5-file.php
	 * 
	 * @param string $file_path_1 path to first file
	 * @param string $file_path_2 path to second file 
	 * 
	 * @return boolean depends if files identical or not 
	 */
	public static function compare_files($file_path_1 = '', $file_path_2 = '')
	{
		//	md5_file
		if (!file_exists($file_path_1) || !file_exists($file_path_2)) {
			return false;
		}

		return (md5_file($file_path_1) === md5_file($file_path_2));

	}

	/**
	 * Downloads remote files from other server 
	 * 
	 * @param string $url path to remote server 
	 * @param string $filename name of file to be stored locally 
	 * 
	 * @return $filename|boolean path of file that is being stored or boolean upon success or fail 
	 */
	public static function remote_download($url = '', $filename = '')
	{
		// SECURITY FIX: Validate URL
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			throw new InvalidArgumentException('Invalid URL: ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
		}

		// SECURITY FIX: Only allow HTTPS for sensitive downloads
		if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
			throw new InvalidArgumentException('Only HTTPS URLs are allowed');
		}

		// SECURITY FIX: Validate filename
		$filename = str_replace("\0", '', $filename);
		if (!preg_match('/^[a-zA-Z0-9._-]+$/', basename($filename))) {
			throw new InvalidArgumentException('Invalid filename');
		}

		set_time_limit(0);
		$file = fopen($filename, 'w+');
		if ($file === false) {
			throw new RuntimeException('Could not open file for writing');
		}

		$curl = curl_init($url);

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_BINARYTRANSFER => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FILE => $file,
			CURLOPT_TIMEOUT => 50,
			CURLOPT_SSL_VERIFYPEER => true,  // SECURITY FIX: Enable SSL verification
			CURLOPT_SSL_VERIFYHOST => 2,     // SECURITY FIX: Verify hostname
			CURLOPT_USERAGENT => 'WP-SafeMode/1.0',
			CURLOPT_FOLLOWLOCATION => false, // SECURITY FIX: Don't follow redirects automatically
			CURLOPT_MAXREDIRS => 0
		));

		$response = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ($response === false) {
			$error = curl_error($curl);
			curl_close($curl);
			fclose($file);
			throw new \Exception('Curl error: ' . $error);
		}

		// SECURITY FIX: Check HTTP status code
		if ($http_code !== 200) {
			curl_close($curl);
			fclose($file);
			unlink($filename); // Clean up failed download
			throw new \Exception('HTTP error: ' . $http_code);
		}

		curl_close($curl);
		fclose($file);

		return $filename;
	}

	/**
	 * Sends remote post request. This should be just a wrapper for remote request. 
	 * 
	 * @param string $url
	 * @param array $data
	 * 
	 * @return mixed results from remote request 
	 */
	public static function remote_post_request($url = '', $data = array())
	{
		if (empty($url)) {
			return false;
		}

		// SECURITY FIX: Validate URL
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			throw new InvalidArgumentException('Invalid URL: ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
		}

		// SECURITY FIX: Only allow HTTPS
		if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
			throw new InvalidArgumentException('Only HTTPS URLs are allowed');
		}

		$post_string = http_build_query($data);

		//create cURL connection
		$curl_connection = curl_init($url);

		//set options
		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl_connection, CURLOPT_USERAGENT, "WP-SafeMode/1.0");
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, true);  // SECURITY FIX: Enable SSL verification
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYHOST, 2);     // SECURITY FIX: Verify hostname
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, false); // SECURITY FIX: Don't follow redirects
		curl_setopt($curl_connection, CURLOPT_MAXREDIRS, 0);

		//set data to be posted
		curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);

		//perform our request
		$result = curl_exec($curl_connection);

		if ($result === false) {
			$error = curl_error($curl_connection);
			curl_close($curl_connection);
			throw new Exception('Curl error: ' . $error);
		}

		$http_code = curl_getinfo($curl_connection, CURLINFO_HTTP_CODE);

		//close the connection
		curl_close($curl_connection);

		// SECURITY FIX: Check HTTP status code
		if ($http_code < 200 || $http_code >= 300) {
			throw new Exception('HTTP error: ' . $http_code);
		}

		return $result;
	}

	/**
	 * Scans given directory recursively 
	 * 
	 * @param string  $dirpath path to directory to be scanned 
	 * 
	 * @return $files array list of files from directory with full paths 
	 */
	public static function scan_directory_recursive($dirpath = '')
	{
		$dirpath = str_replace('\\', '/', $dirpath);
		$di = new RecursiveDirectoryIterator($dirpath, RecursiveDirectoryIterator::SKIP_DOTS);
		$it = new RecursiveIteratorIterator($di);

		//skip files 
		$files = array();
		foreach ($it as $file) {

			$filepath = str_replace('\\', '/', $file->getPathname());
			$filepath_trimmed = str_replace($dirpath, '', $filepath);
			$file_md5 = md5_file($filepath);
			$files[$filepath_trimmed] = array(
				'filepath_full' => $filepath,
				'filepath' => $filepath_trimmed,
				//'filepath_full' => str_replace('\\','/', $file->getPathname()),
				'filename' => $file->getFilename(),
				'md5' => $file_md5,
				//add md5 
			); //Add some more options 

		}

		return $files;
	}

	/**
	 * 
	 * @param string|array $callback 
	 * @param string|array $value
	 * 
	 * @return mixed
	 */
	public static function validate_input_value($callback, $value)
	{

		if (is_callable($callback)) {
			return call_user_func($callback, $value);
		}

		return true;

	}

	/**
	 * 
	 * @param string|array $callback 
	 * @param string|array $value
	 * 
	 * @return
	 */
	public static function filter_input_value($callback, $value)
	{

		if (is_callable($callback)) {
			return call_user_func($callback, $value);
		}

		return $value;

	}


	/**
	 * 
	 * Stores given data in file with given filename, returns true or false. Option to convert to json if content is array, 
	 * and to skip file writting if file $overwrite is set to false
	 * 
	 * @param string $filename path of file that will be created 
	 * @param string|array $file_contents content that will be stored in file. Can be array or string 
	 * @param boolean $to_json if set to true, and if content is array, it will convert it to json format
	 * @param boolean $overwrite
	 * 
	 * @return boolean true or false depending on successfully written contents to file 
	 */
	public static function put_data($filename = '', $file_contents = '', $to_json = false, $overwrite = true, $serialize = false)
	{
		if ($to_json == true) {
			if (is_array($file_contents)) {
				$file_contents = json_encode($file_contents);
			}
		}
		if ($serialize == true) {
			if (is_array($file_contents)) {
				$file_contents = serialize($file_contents);
			}
		}
		if ($overwrite == false) {
			if (file_exists($filename)) {
				return false;
			}
		}
		$file_contents = trim($file_contents);
		return file_put_contents($filename, $file_contents);
	}

	/**
	 * returns content of file with given path or boolean if not found, calls get_file() method
	 * 
	 * @param string $filename path of the file
	 * @param boolean $to_array if set to true, it will convert json format 
	 * 
	 * @return string, aray or boolean - returns content in string format or in array format or false if content not found
	 */
	public static function get_data($filename = '', $to_array = false)
	{
		$file_contents = self::get_file($filename);
		$json = self::is_json($file_contents);
		if ($file_contents) {
			if ($to_array == true) {
				if ($json) {
					return (array) json_decode($file_contents);
				}

			}
			return $file_contents;
		}

	}
	public static function get_file($filename = '')
	{
		if (file_exists($filename)) {
			return file_get_contents($filename);
		}
		return false;
	}

	public static function is_json($string)
	{
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	public static function check_directory($filename = '', $create = true)
	{
		// SECURITY FIX: Validate path
		if (is_string($filename)) {
			$filename = str_replace("\0", '', $filename);
		}

		if (!is_array($filename)) {
			// Check if already exists
			if (file_exists($filename)) {
				return true;
			}

			// Only try to create if requested
			if ($create == true) {
				// Check parent directory permissions
				$parent_dir = dirname($filename);
				if (!is_dir($parent_dir) && !mkdir($parent_dir, 0755, true)) {
					$error = error_get_last();
					$error_msg = $error ? $error['message'] : 'Unknown error';
					error_log('Failed to create parent directory: ' . $parent_dir . ' - ' . $error_msg);
					return false;
				}

				// SECURITY FIX: Use secure permissions (0755 instead of 0777)
				// Suppress warnings and check result
				if (!@mkdir($filename, 0755, true)) {
					$error = error_get_last();
					error_log('Failed to create directory: ' . $filename . ' - Error: ' . ($error ? $error['message'] : 'Unknown'));
					return false;
				}
			}
			return true;
		} else {
			foreach ($filename as $dir) {
				$dir = str_replace("\0", '', $dir);

				// Check if already exists
				if (file_exists($dir)) {
					continue;
				}

				if ($create == true) {
					// Check parent directory permissions
					$parent_dir = dirname($dir);
					if (!is_dir($parent_dir) && !@mkdir($parent_dir, 0755, true)) {
						$error = error_get_last();
						$error_msg = $error ? $error['message'] : 'Unknown error';
						error_log('Failed to create parent directory: ' . $parent_dir . ' - ' . $error_msg);
						continue;
					}

					// SECURITY FIX: Use secure permissions (0755 instead of 0777)
					if (!@mkdir($dir, 0755, true)) {
						$error = error_get_last();
						error_log('Failed to create directory: ' . $dir . ' - Error: ' . ($error ? $error['message'] : 'Unknown'));
						continue;
					}
				}
			}
		}
		return true;
	}


	/**
	 * Set message to be displayed on next page load
	 * 
	 * @param string $message Message text
	 * @return void
	 */
	public static function set_message($message)
	{
		if (empty($message)) {
			return;
		}
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
		if (!defined('Constants::SESSION_MESSAGE_KEY') && !defined('SESSION_MESSAGE_KEY')) {
			// Fallback if constant not defined
			$key = 'wpsafemode_message';
		} else {
			$key = defined('Constants::SESSION_MESSAGE_KEY') ? Constants::SESSION_MESSAGE_KEY : SESSION_MESSAGE_KEY;
		}

		// Use hardcoded key for now to avoid dependency issues if Constants not loaded
		$key = 'wpsafemode_message';
		if (class_exists('Constants') && defined('Constants::SESSION_MESSAGE_KEY')) {
			$key = Constants::SESSION_MESSAGE_KEY;
		}

		if (!isset($_SESSION[$key])) {
			$_SESSION[$key] = '';
		}
		$_SESSION[$key] .= $message . '<br/>';
	}

	/**
	 * Retrieves messages stored in session
	 * Cleans up session after
	 * 
	 * @return string
	 */
	public static function get_message()
	{
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		$key = 'wpsafemode_message';
		if (class_exists('Constants') && defined('Constants::SESSION_MESSAGE_KEY')) {
			$key = Constants::SESSION_MESSAGE_KEY;
		}

		if (isset($_SESSION[$key])) {
			$message = $_SESSION[$key];
			unset($_SESSION[$key]);
			return $message;
		}
		return '';
	}
}