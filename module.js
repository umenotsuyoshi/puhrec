// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

M.mod_puhrec = {};

/**
 * The JavaScript used in the puhrec activity module
 *
 * recognition.continuousについて
 * デフォルト設定は、recognition.continuous=false。
 * recognition.continuous=falseのままだと音声認識ができた時点でonresultイベントが発生し、以降継続録音しても認識はされない（文字化されない）
 * recognition.continuous=trueにすると、継続して認識されるが、録音音声が一拍遅れて聞こえてくるようになる現象が発生する。
 * 自分の声のピッチが遅くなって聞こえるため非常に発話し難い。（遅れるのではなくスロー再生されるているように感じる）
 * recognition.continuous=falseだと遅れがないのではなく目立たないだけかもしれない。
 *
 * TODO:調査・調整要。
 *
 * recognition.interimResults = false // 中間解析結果を返さない。defalut=false
 *
 * @author
 * @author
 * @package    mod
 * @subpackage puhrec
 * @copyright
 * @license
 * @version
 */
M.mod_puhrec.init = function(yui, maxduration, textlang) {
    navigator.getUserMedia = (navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia);
    var mediaStream = null; // http://www.w3.org/TR/mediacapture-streams/#mediastream
    var mediaRecorder = null;// http://www.w3.org/TR/mediastream-recording/
    var buffArray  = new Array();
    var limitTimerID = 0;
    var restart_maxduration = maxduration;
    var rec_satus = false;
    /*
     * 現在（2016.08）Chromeは、mediaRecorder.ondataavailable(e)のe.data.typeを空で呼び出す。
     * デフォルト値をChrome(audio/webm)にしておいて、Firefox（audio/ogg)で上書きで回避。
     */
    var dataType = 'audio/webm';
    var rec_level_meter = document.getElementById("rec_level_meter");
    var rlmeter_context=null;
    var meter_width=null;
    var meter_height=null;
    var gradient=null;
    // レベルメータ関連 録音数がMAXになると録音ツール類が表示されなくなるため、UI要素がない場合の判定要
    if(rec_level_meter != null){
        rlmeter_context = rec_level_meter.getContext("2d");
        meter_width = rec_level_meter.width;
        meter_height = rec_level_meter.height;
        gradient = rlmeter_context.createLinearGradient(0,0,0,meter_height);
        gradient.addColorStop(0.99,'#00ff00');
        gradient.addColorStop(0.02,'#0000ff');
        gradient.addColorStop(0.01,'#ff0000');
    }
    var audioBuffer;
    var audioContext = new AudioContext();
    var sourceNode = null;
    var analyser = null;
    // 音声認識機能追加
    var SpeechRecognition = (SpeechRecognition || webkitSpeechRecognition);
    var recognizing;
    var recognition = new SpeechRecognition();
    recognition.continuous = true; // 連続音節認識
    recognition.interimResults = false // 中間解析結果を返さない
    recognition.lang = textlang;
//    recognition.onend = reset; //
    recognition.onresult = function (event) {
      for (var i = event.resultIndex; i < event.results.length; ++i) {
        var result = event.results[i];
        for(var j=0;j<result.length;j++){
            console.log(result[j].transcript);
            $("#taran_text").text($("#taran_text").html()+ result[j].transcript + " ");
        }
      }
    }
    /**
     *
     * getVoices()で取得できる音声の種類がブラウザ、OSによって異なっている。
     * 言語が一致するもので選択しているが、ブラウザにより複数音声ある場合もあり。
     * 音声を指定できるようにするか要検討。ブラウザ、OSによる品揃えについて調査未
     *
     * localService:trueとはブラウザ組み込みか？
     * Google XX (ex. Google US English）がfalse.取れないときあり？
     *
     * SpeechSynthesisVoice{
     * default:false
     * lang:ja-JP
     * localService:true
     * name:"Microsoft Haruka Desktop - Japanese"
     * voiceURI:"urn:moz-tts:sapi:Microsoft Haruka Desktop - Japanese?ja-JP"
     * }
     *
     *
     */
    $("#puhrec_play").on('click', function () {
        var p_txt = new SpeechSynthesisUtterance();
        p_txt.text = $('#puhrec_playtxt').text();
        p_txt.lang = textlang;     // 言語
        var voices = window.speechSynthesis.getVoices();
        p_txt.voice = null;
        voices.forEach(function(v, i){
            if(v.lang == textlang)
            {
                if(p_txt.voice == null){
                    p_txt.voice = v; // Google音声が取れたり取れなかったりしているためか、何度もF5を押すと音声変わる？？？？
                }
                console.log("SELECTED SpeechSynthesisVoice.name:" + v.name);
            }
            console.log("SpeechSynthesisVoice.name:" +  v.name +" : " + v.lang + " : " + v.localService);
        });
        speechSynthesis.speak(p_txt);
        $("#puhrec_pcount").text(parseInt($("#puhrec_pcount").text()) + 1)
    });

    /**
     * ユーザの録音許可
     * ユーザの許可を得る必要があるのは、Firefoxだけ。
     * Chromeでユーザの許可を得るためのダイアログが表示されなくなり、すぐに録音が開始されるようになっている。
     * 許可を得ると録音開始する。
     *
     */
    $("#puhrec_rec").on('click', function () {
        try{
            navigator.getUserMedia({
                video : false,
                audio : true
            }, function(stream) {
                mediaStream = stream;
                sourceNode = audioContext.createMediaStreamSource(stream);
                analyser = audioContext.createAnalyser();
                analyser.smoothingTimeConstant = 0.3;
                analyser.fftSize = 1024;
                sourceNode.connect(analyser);
                //analyser.connect(audioContext.destination);// コメントを外すと録音中に自分の声が聞こえるが、その音声に遅れがある。録音音声も不自然になる。
                function　dispPeakMeter() {
                    var array =  new Uint8Array(analyser.frequencyBinCount);
                    analyser.getByteFrequencyData(array);
                    var max = getMaxVolume(array);
                    max = (max*meter_height)/255;
                    rlmeter_context.clearRect(0, 0, meter_width, meter_height);
                    rlmeter_context.fillStyle=gradient;
                    rlmeter_context.fillRect(0,meter_height - max,meter_width,max);
                    function getMaxVolume(array) {
                        var length = array.length;
                        var max = 0; // 255
                        for (var i = 0; i < length; i++) {
                            if(max < array[i])max=array[i];
                        }
                        return max;
                    }
                    requestID = requestAnimationFrame(dispPeakMeter);
                }
                dispPeakMeter();
                // ハウリングするので停止
                //sourceNode.connect(audioContext.destination);
                rec_start();
            }, function(err) {
                console.log(e);
                alert(err.name ? err.name : err);
            });
        }catch(e){
            console.log(e);
            browser_error(e);
        }
    });
    /**
     * getUserMediaはChrome, Operaの場合は、httpsでなければ動作しなくなった。
     * Ver.0．X時は警告を出しておく。
     * OperaのレンダリングエンジンChromeと同じ。
     * UAにOperaの文字ないので注意。（2016.07.27）
     * ex."mozilla/5.0 (windows nt 10.0; wow64) applewebkit/537.36 (khtml, like gecko) chrome/51.0.2704.106 safari/537.36 opr/38.0.2220.41"
     */
    browser_error = function(e){
        var href = window.location.href ;
        var ua = window.navigator.userAgent.toLowerCase();
        if(href.indexOf('https://')<0 && ua.indexOf('chrome')>0){
            alert(M.str.puhrec.changeserver);
        }else{
            alert(M.str.puhrec.changebrowser);
        }
    }

    /**
     * 録音開始
     *
     * MediaRecorder(stream	MediaStream,options	MediaRecorderOptions)
     * MediaRecorderの第２引数でmimeType指定できる。
     * しかし、audioについては、Chromeはaudio/webmのみ、Firefoxはaudio/oggのみサポートの様。
     * MediaRecorder.isTypeSupported()
     * ChromeはコンストラクタでmimeType指定してもondataavailableのe.data.typeが空。
     * サポートするmimeTypeを返すAPIはなし。（W3C Working Draft 20 June 2016）
     * 現在は、第2パラメータを指定しても意味なし。
     * ビデオの場合だがコーデック指定も可能
     * ex. options = {mimeType: 'video/webm, codecs=vp9'};
     */
    function rec_start() {
        $("#taran_text").empty();
        recognition.start(); //

        maxduration = restart_maxduration;
        buffArray  = new Array();
        try{
            mediaRecorder = new MediaRecorder(mediaStream);
            /**
             * chromeはondataavailableが小刻みに発生する。音声データはe.dataを結合したもの。
             * timesliceを指定しない場合、FirefoxはmediaRecorder.stop()呼出し後一度だけ発生。
             * chromeはtypeが空。バイナリエディタで見るとメディア・タイプはaudio/webm
             * Firefoxはaudio/ogg。
             *
             */
            mediaRecorder.ondataavailable = function(e) {
                buffArray.push(e.data);
                if('' != e.data.type){
                    dataType = e.data.type;　// Firefoxだけ指定してくる
                }
            }
            /**
             * mediaRecorder.startの直後停止ボタンを有効にすると録音、停止と連続してボタンされ
             * startする前に停止処理が走行する。タイマーが止まらない。
             * onstart発生後でもタイマーが停止しない場合があり（録音は停止）、録音中フラグを設定。
             */
            mediaRecorder.onstart = function(e) {
                $("#puhrec_stop").removeAttr('disabled');
                rec_satus = true;
                limitTimerID = limit_timer();
            }
            var timeslice = 1000; // The number of milliseconds of data to return in a single Blob.
            mediaRecorder.start(timeslice);
            $("#puhrec_rec").attr('disabled','disabled');
            $("#puhrec_check").attr('disabled','disabled');
            $("#puhrec_recording_audio")[0].src='';
            $("#puhrec_upload").attr('disabled','disabled');

            /* */
        }catch(e){
            console.log(e);
            clearTimeout(limitTimerID);
            alert(M.str.puhrec.changebrowser);
        }
    }
    /**
     * 停止ボタン　
     * start時にtimeslice指定してUAの挙動揃える。
     * mediaRecorder.start(timeslice);
     *
     */
    $("#puhrec_stop").on('click', function () {
        stop_recording();
    });
    /**
     * 録音停止
     */
    stop_recording = function(){
        recognition.stop();
        clearTimeout(limitTimerID);
        mediaRecorder.stop();
        mediaStream.getAudioTracks()[0].stop();
        rec_satus = false;
        $("#puhrec_rec").removeAttr('disabled');
        $("#puhrec_stop").attr('disabled','disabled');
        $("#puhrec_check").removeAttr('disabled');
        $("#puhrec_upload").removeAttr('disabled');
    }
    /**
     * 確認ボタン
     */
    $("#puhrec_check").on('click', function () {
        scorerecording();
        blob = new Blob(buffArray , { type : dataType }); // blobオブジェクトは.typeと.sizeのみ
        if(blob.size==0){
            alert(M.str.puhrec.changebrowser);
            return false;
        }
        var blobUrl = window.URL.createObjectURL(blob);
        $("#puhrec_recording_audio")[0].src= blobUrl;
        $("#puhrec_recording_audio")[0].play();
    });
    /**
     * 録音音声自動採点
     */
    function scorerecording(){
        var sample = $('#puhrec_playtxt').text();
        var reced = $("#taran_text").text();
        sample=sample.replace(/[\s]+/g,'');
        reced=reced.replace(/[\s]+/g,'');
        var score = levenshteinDistance(sample, reced);
        var len =(sample.length > reced.length)?sample.length : reced.length;
        score = Math.round(((len - score )/len) * 100);
        $("#levenshteinDistance").empty();
        $("#levenshteinDistance").text(score + "点");
    }
    /**
     * アップロードボタン
     *
     * Blobのままアップロードするのでname属性がない。typeにはaudio/ogg（Firefoxの場合）
     * がブラウザにより設定されている。
     * FirefoxではOggフォーマットで録音され、OggはFirefox、Chrome、Operaでは再生可能。
     * Edge,IEはOgg、webmの再生未サポート。Edgeでは録音だけでなく、再生も不可。
     * （Blobはtype、size属性を持つ。FileはBlobを継承。name属性が追加）
     *
     * https://developer.mozilla.org/ja/docs/Web/API/FormData
     * のブラウザ実装状況の部分要確認。元は空文字が送信されていた。
     * 今はFormDataにblobをappendした場合、"blob"がファイル名として送信される。
     *
     *  Formをsubmitするとファイルシステムにアクセスしエラーとなる。
     *  実際にファイルシステム上からファイルをアップロードしようとしている模様。
     *  BlobからFileをnewしようとするとTypeError: Value can't be converted to a dictionary.が発生
     *
     */
    $("#puhrec_upload").on('click', function () {
        if( $('#puhrec_rec_comment').hasClass("not_changed")== true)$('#puhrec_rec_comment').val("");
        blob = new Blob(buffArray , { type : dataType }); // blobオブジェクトは.typeと.sizeのみ
        if(blob.size==0){
            alert(M.str.puhrec.changebrowser);
            return false;
        }
        var formdata = new FormData($('#voice_send').get(0));
        formdata.append( "status", $("#status").val() ); // 使っていない
        formdata.append("tarantext",$("#taran_text").text());
        formdata.append("playcount",$("#puhrec_pcount").text());
        formdata.append( "puhrec_upload_file", blob );
        var postData = {
                type : "POST",
                dataType : "text",
                data : formdata,
                processData : false,
                contentType : false
        };
        $.ajax( "./saveaudio.php", postData ).done(function( text ){
            if(!M.str.puhrec[text]){
                //alert(text); //debug時に開ける。
            }else{
                //alert(text); //debug時に開ける。
                alert(M.str.puhrec[text]);
            }
            console.log( text );
            location.reload();
        });
        $("#puhrec_rec").removeAttr('disabled');
        $("#puhrec_stop").attr('disabled','disabled');
        $("#puhrec_check").attr('disabled','disabled');
        $("#puhrec_recording_audio")[0].src='';
        $("#puhrec_upload").attr('disabled','disabled');
    });
    /**
     * 録音制限時間タイマー
     */
    limit_timer = function(){
        $('#rectime_timer').text(maxduration);
        maxduration--;
        if(maxduration <= 0){
            stop_recording();
            alert(M.str.puhrec.timeoutmessage);
            return;
        }
        if(maxduration <= 10){
            $('#rectime_timer').css('color','red!important');
        }
        if(rec_satus == true){
            limitTimerID = setTimeout(limit_timer, 1000);
        }
    }



    /* 以下mod puhrec固有部分 */

    /*
     * 録音コメント
     */
    $('#puhrec_rec_comment')
    .focusin(function() {
        $('#puhrec_rec_comment').val("");
        $('#puhrec_rec_comment').removeClass("not_changed");
    })
    .focusout(function(){
        if(M.str.puhrec.inputrectitle == $('#puhrec_rec_comment').val() ||
            $('#puhrec_rec_comment').val() == ''){
            $('#puhrec_rec_comment').addClass("not_changed");
            $('#puhrec_rec_comment').val(M.str.puhrec.inputrectitle);
        }
    })

    /*
     * 編集画面へ遷移
     */
    $('.puhrec_editgrade_button').on("click",function(){
        location = $(this).attr('action');
    });
    /**
     * レーベンシュタイン距離
     */
    function levenshteinDistance( str1, str2 ) {
        var x = str1.length;
        var y = str2.length;

        var d = [];
        for( var i = 0; i <= x; i++ ) {
            d[i] = [];
            d[i][0] = i;
        }
        for( var i = 0; i <= y; i++ ) {
            d[0][i] = i;
        }
        var cost = 0;
        for( var i = 1; i <= x; i++ ) {
            for( var j = 1; j <= y; j++ ) {
                cost = str1[i - 1] == str2[j - 1] ? 0 : 1;
                d[i][j] = Math.min( d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost );
            }
        }
        return d[x][y];
    }
}


