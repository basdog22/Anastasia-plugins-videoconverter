<?php

\Route::group(array('before' => 'isadmin','as'=>'admin','prefix'=>'backend'),function(){

    \Route::get('videoconverter/manage','Plugins\videoconverter\controllers\VideoConverterController@manage');
    \Route::get('videoconverter/convert/{videoid}/{tofile}','Plugins\videoconverter\controllers\VideoConverterController@convert');
    \Route::get('videoconverter/resize/{videoid}/{newsize}','Plugins\videoconverter\controllers\VideoConverterController@resize');
    \Route::get('videoconverter/watermark/{videoid}/{newsize}','Plugins\videoconverter\controllers\VideoConverterController@watermark');
    \Route::get('videoconverter/frames/{videoid}/{frames}','Plugins\videoconverter\controllers\VideoConverterController@frames');

});