<?php

/*

	Steam Background Finder
    Copyright (C) 2018 Xxmarijnw & Modified by KagurazakaSanae

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published
    by the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
	
*/

@$link = $_POST['url'];

if(trim($link) == '' || !filter_var($link, FILTER_VALIDATE_URL)){
	echo 'Oops! You forgot to enter something.';
	die;
}

if(!stristr($link, 'steamcommunity.com')){
	echo 'Please be sure you are providing an valid Steam community profile URL.';
	die;
}

$profile = urllib('GET', $link);

preg_match('/<div class="error_ctn">/', $profile, $profile_exists);
if($profile_exists){
	echo 'Oops! This profile was not found.';
	exit();
}
	
preg_match('/<div class="profile_private_info">/', $profile, $profile_private);
if($profile_private){
	echo 'Oops! This profile is set to private.';
	exit();
}

$background = getUserProfileBGUrl($link);
if($background == -1){
	echo 'Oops! This profile has no background.';
	exit();
}

$bg_data = getBGMarketUrl($background);
if($bg_data == -2 or $bg_data == -1 or $bg_data == -4){
	echo 'Oops! Our server may be temporarily blocked by Steam, please try again later';
	die;
}
if($bg_data == -3 || $bg_data == -5){
	echo 'Oops! We can not fetch out any results that match this background, please try again later';
	die;
}

$appid = $bg_data['appid'];

$app_details = json_decode(urllib('GET', 'https://store.steampowered.com/api/appdetails?appids='.$appid), true);
@$app_name = $app_details[$appid]['data']['name'];
$app_name = ($app_name !== null) ? 'This background is from '.$app_name : '';

$steam_card_exchange = 'https://www.steamcardexchange.net/index.php?gamepage-appid-'.$appid;
$steamdesign = 'https://steam.design/#'.$background;
			
$price = '';

$market = $bg_data['url'];
$market_hash_name = $bg_data['hash_name'];
@$price_overview   = json_decode(urllib('GET', "https://steamcommunity.com/market/priceoverview/?appid=753&currency=1&market_hash_name=".$market_hash_name), true);
if(($price_overview['success'] === true) && ($price_overview['lowest_price'] !== null)){
	$price = ' ('.$price_overview['lowest_price'].')';
}

echo json_encode(array('background'=>$background, 'steam_card_exchange'=>$steam_card_exchange, 'market'=>$market, 'app_name'=>$app_name, 'steamdesign'=>$steamdesign, 'price'=>$price));

function getBGMarketUrl($bgurl){
	$t = explode('/', $bgurl);
	$appid = $t[array_search('items', $t) + 1];
	$filename = $t[array_search('items', $t) + 2];
	$market_search_contents = urllib('GET', 'https://steamcommunity.com/market/search?category_753_Game%5B%5D=tag_app_' . $appid . '&category_753_item_class%5B%5D=tag_item_class_3&appid=753');
	if(!stristr($market_search_contents, 'Showing results for')){
		file_put_contents('err.html', $market_search_contents);
		return -2;	//content error
	}
	if(stristr($market_search_contents, 'There were no items matching your search')){
		return -1;	//appid error
	}
	if(preg_match_all('/(<a class="market_listing_row_link" href=)(".*?")( id)(=)(".*?")(>)/is', $market_search_contents, $matches)){
		$links = array();
		foreach($matches[2] as $s){
			$links[] = str_replace('"', '', $s);
		}
	}else{
		return -3;	//no search results
	}
	foreach($links as $s){
		$item_detail_page_contents = urllib('GET', $s);
		if(!stristr($item_detail_page_contents, 'View Full Size')){
			return -4;	//content error2
		}
		if(stristr($item_detail_page_contents, $filename)){
			$title = str_replace('Steam Community Market :: Listings for ', '', explode('</title>', explode('<title>', $item_detail_page_contents)[1])[0]);
			$hash_name = explode('/', $s);
			$hash_name = $hash_name[array_search('listings', $hash_name) + 2];
			return array('name' => $title, 'url' => $s, 'appid' => $appid, 'hash_name' => $hash_name);
		}
	}
	return -5;	//no matching results
}

function getUserProfileBGUrl($profile_url){
	$profile_page_content = urllib('GET', $profile_url);
	if(!stristr($profile_page_content, 'no_header profile_page has_profile_background')){
		return -1;	//can not get background link
	}
	$link = explode('\' );">', explode('<div class="no_header profile_page has_profile_background " style="background-image: url( \'', $profile_page_content)[1])[0];
	return $link;
}

function urllib($function, $url, $data = array(), $header = array()){
	if(!in_array('User-Agent', $header)){
		$header['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36';
	}
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	if(isset($header['Cookie'])){
		curl_setopt($curl, CURLOPT_COOKIE, $header['Cookie']);
	}
	if($function == 'POST'){
		$data = http_build_query($data);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	$r = curl_exec($curl);
	//var_dump(curl_error($curl));
	//var_dump(curl_errno($curl));
	curl_close($curl);
	return $r;
}

?>
