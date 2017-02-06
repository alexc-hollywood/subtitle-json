<?php

// taken from https://stackoverflow.com/questions/11659118/parsing-srt-files

ini_set("auto_detect_line_endings", true);

define('SRT_STATE_SUBNUMBER', 0);
define('SRT_STATE_TIME',      1);
define('SRT_STATE_TEXT',      2);
define('SRT_STATE_BLANK',     3);

$all_timecodes = [];

$subs_en = [];
$subs_fr = [];
$subs_es = [];


$output = new stdClass;
$output->id       = "33834784375375";
$output->title    = "The Shawshank Redemption";
$output->format   = "SubRip";

$output->templates  = [
  'default' => '__CONTENT__',
  'italic'  => '<i>__CONTENT__</i>'
];

$output->styles  = [
  'default' => 'font-style: 10px; line-height: 1; color: #FFF;'
];

$output->data     = [];

function to_ms(string $duration): int {
    $p = explode(':', $duration);
    if( count($p) && isset($p[1]) && isset($p[2]) && isset($p[3]) ) {
      return (int)$p[0] * 3600000 + (int)$p[1] * 60000 + (int)$p[2] * 1000 + (int)$p[3];
    }
    return 0;
}



function parse_srt_file_to_data( $lang = 'en' ) {
  global $all_timecodes;

  $state   = SRT_STATE_SUBNUMBER;
  $subNum  = 0;
  $subText = '';
  $subTime = '';

  $lines   = file('../srt/shawshank_'.$lang.'.srt');

  $subs = [];

  $current = 0;
  $max = 50000;

  foreach($lines as $line) {
    if( $current <= $max ) {
      switch($state) {
          case SRT_STATE_SUBNUMBER:
              $subNum = trim($line);
              $state  = SRT_STATE_TIME;
              break;

          case SRT_STATE_TIME:
              $subTime = trim($line);
              $state   = SRT_STATE_TEXT;
              break;

          case SRT_STATE_TEXT:
              if (trim($line) == '') {
                  $sub = new stdClass;

                  @list($sub->startTime, $sub->stopTime) = explode(' --> ', $subTime);

                  $start_time = to_ms(str_replace(',', ':', $sub->startTime));
                  $end_time   = to_ms(str_replace(',', ':', $sub->stopTime));

                  if( !array_key_exists($start_time, $all_timecodes) ) {
                    $all_timecodes[$start_time] = [

                    ];
                  }

                  $start_div = explode(',', $sub->startTime);
                  $start_parts = explode(':', $start_div[0]);

                  $sub->start = [
                    'time' => floatval($start_time),
                    'hour' => isset($start_parts[0]) ? floatval($start_parts[0]) : 0,
                    'mins' => isset($start_parts[1]) ? floatval($start_parts[1]) : 0,
                    'secs' => isset($start_parts[2]) ? floatval($start_parts[2]) : 0,
                    'ms'   => isset($start_div[1]) ? floatval($start_div[1]) : 0,
                  ];

                  $end_div = explode(',', $sub->stopTime);
                  $end_parts = explode(':', $end_div[0]);

                  $sub->end = [
                    'time' => floatval($end_time),
                    'hour' => isset($end_parts[0]) ? floatval($end_parts[0]) : 0,
                    'mins' => isset($end_parts[1]) ? floatval($end_parts[1]) : 0,
                    'secs' => isset($end_parts[2]) ? floatval($end_parts[2]) : 0,
                    'ms'   => isset($end_div[1]) ? floatval($end_div[1]) : 0,
                  ];

                  $sub->duration = floatval($end_time - $start_time);

                  /*
                  $sub->content   = [
                    'en' => utf8_encode(preg_replace('/\s+/', ' ', str_replace(array("\n","\r"), ' ', trim($subText)))),
                    'fr' => '',
                    'es' => ''
                  ];
                  */

                  $lang_content = strip_tags(htmlentities(preg_replace('/\s+/', ' ', str_replace(array("\n","\r"), ' ', trim($subText))), ENT_COMPAT, 'UTF-8'));
                  $sub->content = $lang_content;




                  $sub->meta = [
                    'original' => [
                      'start' => $sub->startTime,
                      'end' => $sub->stopTime
                    ]
                  ];

                  $sub->align = 'center';

                  $subText     = '';
                  $state       = SRT_STATE_SUBNUMBER;

                  unset($sub->startTime);
                  unset($sub->stopTime);

                  $subs[$start_time]      = $sub;

                  if( !empty($lang_content) ) {
                    $all_timecodes[$start_time]['trigger']  = $start_time;
                    $all_timecodes[$start_time]['lang']     = $lang;
                    $all_timecodes[$start_time]['styles']   = ['default'];
                    if( stristr($subText, '<i>') ) {
                      $all_timecodes[$start_time]['templates']   = ['italic'];
                    } else {
                      $all_timecodes[$start_time]['templates']   = ['default'];
                    }
                    $all_timecodes[$start_time]['start']    = $sub->start;
                    $all_timecodes[$start_time]['end']      = $sub->end;
                    $all_timecodes[$start_time]['duration'] = [
                      'secs' => floatval(number_format($sub->duration / 1000, 4)),
                      'ms' => $sub->duration,
                    ];
                    $all_timecodes[$start_time]['content']  = $sub->content;
                    $all_timecodes[$start_time]['meta']     = $sub->meta;
                  }


                  $current++;
              } else {
                  $subText .= $line;
              }
              break;

      }
    }
  }

  return $subs;

} // end function

$en_subs = parse_srt_file_to_data('en');
$fr_subs = parse_srt_file_to_data('fr');
$es_subs = parse_srt_file_to_data('es');
$pt_subs = parse_srt_file_to_data('pt');
$it_subs = parse_srt_file_to_data('it');

ksort($all_timecodes);

$finalized = [];

foreach($all_timecodes AS $code => $data) {

  if( !count($data) ) {
    unset($all_timecodes[$code]);
  } else {
    array_push($finalized, $data);
  }

}

$output->data = $finalized;

file_put_contents('../json/multi-language/PRETTY_multi_the_shawshank_redemption.json', json_encode($output, JSON_PRETTY_PRINT));
file_put_contents('../json/multi-language/RAW_multi_the_shawshank_redemption.json', json_encode($output));

echo 'done';
