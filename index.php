<?php
	require_once 'phpQuery/phpQuery/phpQuery.php';
	include 'Inc/init.php';
	function configPars(){
		ini_set('max_execution_time', '10000');
		set_time_limit(0);
		ini_set('memory_limit', '4096M');
		ignore_user_abort(true);	
	}

	//Функция для получения страницы по URL
	function getPageByUrl($url) {
		$curl = curl_init();
		  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
		  curl_setopt($curl, CURLOPT_URL, $url);
		  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		  $multi_init = curl_multi_init();

			$result = curl_exec($curl);
			curl_close($curl);

			if($result === false) {			
				echo "Ошибка CURL: " . curl_error($curl);
				return false;
			}else {
				return $result;
			}
	}

	//Функция для получения ссылок категорий
	function getLinksCategory(){
		$linksCategories = [];
		$page = getPageByUrl('https://www.tury.ru/hotel/');
		$pqPage = phpQuery::newDocument($page);
		$categories = $pqPage->find('.best_hotels a');

		foreach($categories as $category){
			$pqCategory = pq($category);
			$nameCategory = $pqCategory->find('h3');
			$linksCategories[$nameCategory->text()] = $pqCategory->attr('href');
		}
		return $linksCategories;
	}

	//Функция для получения ссылок категорий из ajax запросов
	function getRequestsUries($linksCategories){
		$linksRequests = [];
		foreach($linksCategories as $nameCategory=>$category){
			$pageCategory = getPageByUrl('https://www.tury.ru'.$category);
			$pqPageCategory = phpQuery::newDocument($pageCategory);
			$scriptCategory = $pqPageCategory->find('script');
			preg_match_all('#load\("(.+?)&s="\+last_s#su',$scriptCategory , $matches);
			$linksRequests[$nameCategory] = $matches[1][0];
		}
		return $linksRequests;
	}

	//Функция для получения ссылок отелей со всех категорий, параметром принимает массив ссылок категорий
	function getLinksAllHotels($linksRequest){
		$page = getPageByUrl($linksRequest);
		$linksAllHotels = [];
		foreach($linksRequest as $nameCategory=>$requestUri){
			$linksAllHotels[$nameCategory] = [];
			$page = multiCurl($linksRequests);
			var_dump($page);
			$pqPage = phpQuery::newDocument($page);
			$elemsHotels = $pqPage->find('.hotel_head_dv a.hotel_name');
			foreach($elemsHotels as $elemHotel){
				$pqElemHotel = pq($elemHotel);
				$linksAllHotels[$nameCategory][] = $pqElemHotel->attr('href');
			}
		}
		return $linksAllHotels;
	}


	//Функция для поиска и сохранения в БД необходимых данных по категориям отелей, параметром принимает подключениие к БД и ссылки всех отелей
	function getContentsAndSaveInTable($link, $linksAllHotels){
		$arrContents = [];
		foreach($linksAllHotels as $nameCategory=>$linksHotels){
			$arrContents[$nameCategory] = [];
			$query = "INSERT INTO `category_tury` SET name='$nameCategory'";
			mysqli_query($link, $query) or die(mysqli_error($link));
			$id = mysqli_insert_id($link);
			foreach($linksHotels as $linkHotel){
				$pageHotel = getPageByUrl("https://www.tury.ru$linkHotel");
				$pqPageHotel = phpQuery::newDocument($pageHotel);
				$elemTitle = $pqPageHotel->find('tr[valign="top"] [itemprop="name"]');
				$elemContent = $pqPageHotel->find('.hotel_info_block [itemprop="description"]');
				$elemImg = $pqPageHotel->find('.hotel_pics img:first');
				$src = $elemImg->attr('src');
				 $srcImg = file_get_contents('https:'.$src);
				 $nameImg = getRandomText();
				 file_put_contents("images/$nameImg.jpg", $srcImg);
				 $title = mysqli_real_escape_string($link,$elemTitle->text());
				 $content = mysqli_real_escape_string($link,$elemContent->text());

				$query = "INSERT INTO `tury` SET title='$title', `text`='$content', `img`='$nameImg',`category_id`='$id'";
				mysqli_query($link, $query) or die(mysqli_error($link));
			}	
			
		}
		return $arrContents;
	}

	function getRandomText(){
		$str = '';
		for($i = 1; $i <= 8; $i++){
			$str .= chr(mt_rand(65,90));
		}

		return $str;
	}



	//Многопоточность
	/*Функция для многопоточного парсинга, параметром принимает ссылки категорий и их названия
   Необычные
	SPA отели
	Романтические
	Свадебные
	Развлекательные
	Семейные
	Пляжные
	Спортивные
	Горнолыжные */
	function getLinksCategoryHotels($linkRequest, $nameCategory){
		$linksAllHotels = [];
		$linksAllHotels[$nameCategory] = [];
		$page = getPageByUrl($linkRequest);
		$pqPage = phpQuery::newDocument($page);
		$elemsHotels = $pqPage->find('.hotel_head_dv a.hotel_name');
		foreach($elemsHotels as $elemHotel){
			$pqElemHotel = pq($elemHotel);
			$linksAllHotels[$nameCategory][] = $pqElemHotel->attr('href');
		}
		return $linksAllHotels;
	}

	

	//Функция оболочка принимает подключение к БД и название категории
	function parsCategory($link, $category){
		$arr = getRequestsUries(getLinksCategory());
		$arrAllLinks = getLinksCategoryHotels($arr[$category], $category);
		getContentsAndSaveInTable($link, $arrAllLinks);
	}
	function startPars($link){
		if(isset($_GET['pars'])){
			parsCategory($link, $_GET['pars']);
		}
	}

	//Функция создает инпуты с названиями категорий
	function createInputs(){
		$inputs = "";
		$arr = getRequestsUries(getLinksCategory());
		foreach($arr as $key=>$value){
			$inputs .= "<input type=\"hidden\" value=\"$key\">";
		}
		return $inputs;
	}
	$inputs = createInputs();
	configPars();
	startPars($link);


?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document</title>
</head>
<body>
	<?= $inputs ?>
	<script>
		//Функция получает названия категорий с инпутов
		function getCategories(){
			let inputs = document.querySelectorAll('input');
			let categories = [];
			for(input of inputs){
				categories.push(input.value);
			}
			return categories;
		}

		//Функийя отпраляет ajax запросы по каждой категории, обеспечивая многопоточность
		function sendAjaxRequest(){
			let categories = getCategories();
			for(let i = 1; i <= categories.length; i++){
				sleep(1000 * i);
				let promise = fetch('?pars=' + categories[i-1]);
			}
		}

		//Функция для задержки скрипта
		function sleep(milliseconds){
			const date = Date.now();
			let currentDate = null;
			do {
			   currentDate = Date.now();
			} while (currentDate - date < milliseconds);
	   }

	   sendAjaxRequest(); 

	  


	 	

	</script>
</body>
</html>