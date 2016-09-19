<?php

//header('Content-Type: text/plain');

$url = 'http://vod.wordpress.ac.il/wp-json/wp/v2/media';
$json = ws_get($url);

foreach($json as $vod) {
    echo $vod['mime_type'].'<br>';
    if ($vod['mime_type'] == 'video/mp4') {
        print_r($vod);
        echo "<hr>";
        echo $source = $vod['guid']['rendered'].'<br>';
        //echo $source = $vod['media_details']['source_url'].'<br>';
        echo $size = $vod['media_details']['filesize'].'<br>';
        echo date_format(date_create($vod['date']),"Y/m/d")."<br>";
        echo 'st='.$shorttitle = $vod['title']['rendered'].'<br>';
        //$found = preg_match('/'.hebrev('השיעור').'/i', $vod['media_details']['source_url']);// > 0)?"found":"not found";
        //$found = mb_strpos(" ".$vod['title']['rendered']." ", 'השיעור');// > 0)?"found":"not found";
        //echo 'found='.$found;
        echo $title = $vod['caption'].'<br>';
        echo $description = $vod['description'].'<br>';
        preg_match('/unickoid=(\d{1,4})+/',$description, $matches);
        echo $unickoid = $matches[1].'<br>';
        preg_match('/mcid=(\d{1,4})+/',$description, $matches);
        echo $mcid = $matches[1].'<br>';
        preg_match('/mrid=(\d{1,4})+/',$description, $matches);
        echo $mrid = $matches[1].'<br>';

        //echo $thimbwslink = $vod['_links']['wp:featuredmedia'][0]['href'].'<br>';
        $json_thimblink = ws_get($vod['_links']['wp:featuredmedia'][0]['href']);
        echo $thumblink = $json_thimblink['guid']['rendered']."<br>";
        echo "<hr>";
    }
}

//    'shorttitle' => $voditem->title,
//    'thumbnail_title' => $voditem->subject,
//    'title' => $voditem->summary, // This is a hack so we accept this file by extension.
//    //'thumbnail' => $thumb->url,
//    //'thumbnail_width' => (int)$thumb->width,
//    //'thumbnail_height' => (int)$thumb->height,
//    'size' => $voditem->description,
//    'date' => $voditem->imprint,
//    'author' => $voditem->author,
//    //'source' => 'http://services.levinsky.ac.il/wp-vod/movies'.$voditem->path,
//    'source' => 'http://services.levinsky.ac.il/wp-vod/wp-content/uploads/vod/2200_129543_copy.mp4',

function ws_get($url) {
    //$url = 'http://davidsonvod.weizmann.ac.il/wordpress/wp-json/wp/v2/media';
//$content = file_get_contents($url);

    $ch = curl_init(); //curl handler init

    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);// set optional params
    curl_setopt($ch,CURLOPT_HEADER, false);

    $result=curl_exec($ch);

    curl_close($ch);

//echo $result;
//die;

    return $json = json_decode($result, true);
}

// todo: convert mp4 file name (wp post title) to wp media meta
/*
SELECT post_title
,REGEXP_SUBSTR(REGEXP_SUBSTR(post_title,'unickoid=(.)+_'),'(\\d){1,3}+') as 'unickoid'
,REGEXP_SUBSTR(REGEXP_SUBSTR(post_title,'mcid=(.)+_'),'(\\d){1,3}+') as 'mcid'
,REGEXP_SUBSTR(REGEXP_SUBSTR(post_title,'mrid=(.)+_'),'(\\d){1,3}+') as 'mrid'
,REGEXP_SUBSTR(REGEXP_SUBSTR(post_title,'date=(.)+_'),'(\\d*)-(\\d*)-(\\d*)') as 'date'
,TRIM(LEADING '=' FROM REGEXP_SUBSTR(REGEXP_SUBSTR(post_title,'roomname=(.)+'),'=(.*)')) as 'roomname'
FROM `wp_posts`
ORDER BY `wp_posts`.`ID`  DESC

UPDATE `wordpress_vod`.`wp_posts` SET `post_title` = TRIM(LEADING '=' FROM REGEXP_SUBSTR(REGEXP_SUBSTR(post_content,'roomname=(.)+'),'=(.*)'))
WHERE `post_mime_type` LIKE 'video/mp4'

UPDATE `wordpress_vod`.`wp_posts` SET `post_title` = REPLACE(post_title,'--','-') WHERE `post_mime_type` LIKE 'video/mp4'

SELECT * FROM `wp_posts` WHERE `post_mime_type` LIKE 'video/mp4'
*/