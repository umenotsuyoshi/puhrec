# puhrec
本モジュールは、Moodle上で録音問題を作成できるMoodleの「活動モジュール」です。
学生は手本の文章を読み上げ録音します。
録音音声を自動認識し、手本にした文章と比較し採点します。
SpeechRecognition（音声認識API）、SpeechSynthesisUtterance（音声合成API）の調査の目的で実験的に作成したものです。


##動作条件
Moodle3.1で開発しました。  
PC上のChromeで正常系のみ動作確認しました。
PC上のChrome、Operaであれば、正常系のみ動作すると予想されます。
廃止予定のAPIも使用していますが、代替となるべきAPIの実装が揃わないのでしばらくの間だけ動作すると思われます。
Navigator.getUserMediaWeb 標準から削除されました。ブラウザの実装を待ってMediaDevices.getUserMediaに変更したソースをアップします。（2018.05）

SpeechRecognitionがChromeとOperaでしか実装されていないのでその他のブラウザは動作しません。（2017.07.27)
また、エラーの配慮もしていません。

##インストール方法
Moodleのmod/下にpuhrecの名前で配置してください。  

## 既知の問題
* 録音のタイマーが録音停止で止まらないことがある。
* 音声認識中のモニター音声がスロー再生で聞こえるため発話しにくい。

##注意事項 Warning
本ソフトウェアに起因するいかなる問題についても私は一切の責任を負いません。予めご了承ください。 本ソフトウェアのライセンスはMoodle上のライセンスに従います。  
Moodle is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by　the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Moodle is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details. 
You should have received a copy of the GNU General Public License along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


