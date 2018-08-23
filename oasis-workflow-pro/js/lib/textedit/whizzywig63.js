var whizzywig_version='Whizzywig 63';
var sel = '';
//fixed 62 link>new window Chrome bug //link an image //td border hint //insHTML deletes selection in Chrome 
//fixed 63 IE9 breaks whereAmI()
//Copyright © 2005-2011 John Goodman - www.unverse.net  *date 110623
//Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
//The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
//THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

var btn=[]; //object containing button strip information 
 btn._w=16; btn._h=16; //btn._f=getDir()+"icons.png"; //set to path to toolbar image
 btn._f=wfPluginUrl + 'img/icons.png' ;
var buttonPath,  //path to custom toolbar button images; "textbuttons" means don't use images
buttonExt,   //extension (e.g. .png) for toolbar button images;  default '.gif'
cssFile,     //url of CSS stylesheet to attach to edit area
jsFile,
imageBrowse, //path to page for image browser
linkBrowse,  //path to page for link browser
idTa,        //id of the textarea (param to makeWhizzyWig)
//OTHER GLOBALS //Whizzy contentWindow, current sel, range, parent, DOM path, popwindow, window, IE?;
oW, sel, rng, papa, trail, ppw, wn=window, msIE=("Microsoft Internet Explorer"==navigator.appName)
sels='',buts='',vals=[],opts=[],dobut=[],whizzies=[],taOrigSize=[];
cssFile = wfPluginUrl + 'css/lib/textedit/whizzywing.css' ;
function makeWhizzyWig(txtArea, controls){ // make a WhizzyWig from the textarea
 idTa=txtArea;
 whizzies[whizzies.length]=idTa;
 if (!document.designMode) {
  if (idTa.nodeName=="TEXTAREA"){tagButs();}
  alert("Whizzywig "+whizzyTranslate("editor not available for your browser"));
  return;
 }
 var taContent=whizzyGetId(idTa).defaultValue ? whizzyGetId(idTa).defaultValue : whizzyGetId(idTa).innerHTML ? whizzyGetId(idTa).innerHTML: ''; //anything in the textarea?
 if (!whizzyGetId(idTa).rows < 5){whizzyGetId(idTa).rows='15';}//IE won't use % from style
 taWidth=whizzyGetId(idTa).style.width ? whizzyGetId(idTa).style.width : whizzyGetId(idTa).cols + "ex";  //grab the width and...
 taHeight=whizzyGetId(idTa).style.height ? whizzyGetId(idTa).style.height : whizzyGetId(idTa).rows + "em";  //...height from the textarea
 taOrigSize[idTa] = {w:taWidth,h:taHeight};
 //Create whizzy container
 var wContainer = document.createElement('div');
 wContainer.id = 'CONTAINER'+idTa+'';
 wContainer.style.width = taWidth;
 var taObject = whizzyGetId(idTa);
 whizzyGetId(idTa).parentNode.replaceChild(wContainer, whizzyGetId(idTa));
 whizzyGetId("CONTAINER"+idTa).appendChild(taObject);
 //End whizzy container
 if (whizzyGetId(idTa).nodeName=="TEXTAREA"){
 whizzyGetId(idTa).style.color='#060';
 whizzyGetId(idTa).style.zIndex='2';
 }else{writeToWhizzy('<input type="hidden" id="wzhid_'+idTa+'" name="'+idTa+'" />')}
 whizzyHideElement(idTa);
 var frm=whizzyGetId(idTa).parentNode;
 while(frm.nodeName != 'FORM'){frm=frm.parentNode}//if not form, keep trying
 addEvt(frm,"submit",syncTextarea);
 writeToWhizzy('<style type="text/css">button {vertical-align:middle;padding:0;margin:1px 0} button img{vertical-align:middle;margin:-1px} select{vertical-align:middle;margin:1px}  .wzCtrl {background:ButtonFace; border:2px outset ButtonShadow; padding:5px;} #sourceTa{color:#060;font-family:mono;}</style>');
 var dsels='formatblock fontname fontsize';
 var dbuts=' bold italic underline | left center right justify | number bullet indent outdent | undo redo | color hilite rule | link image table | clean html spellcheck fullscreen ';
 var tbuts=' tstart add_row_above add_row_below delete_row | add_column_before add_column_after delete_column | table_in_cell';
 var t_end=''; //table controls end, if needed
 btns=(dbuts+tbuts).split(' ');
 for (var i=0,pos=0;i<btns.length;i++) {
  if(btns[i] && btns[i]!='|' && btns[i]!='tstart'){btn[btns[i]]=btn._w*pos++}
 }
 controls=controls ? controls.toLowerCase() : "all";
 if(controls == "all"){controls=dsels +' newline '+ buts + dbuts + tbuts}
 else{controls += tbuts}
 //writeToWhizzy('<div onmouseover="c(\''+idTa+'\')"><div id="CONTROLS'+idTa+'" class="wzCtrl" unselectable="on">');
 writeToWhizzy('<div><div id="CONTROLS'+idTa+'" class="wzCtrl" unselectable="on">');
 gizmos=controls.split(' ');
 for (i=0;i<gizmos.length;i++){
  if (gizmos[i]){ //make buttons and selects for toolbar, in order requested
   if (gizmos[i] == 'tstart') {
    writeToWhizzy('<div id="TABLE_CONTROLS'+idTa+'" style="display:none" unselectable="on">');
    t_end='</div>';
   }
   else if(gizmos[i]=='|'){writeToWhizzy('&nbsp;<big style="padding-bottom:2em">|</big>&nbsp;')}
   else if(gizmos[i] == 'newline'){writeToWhizzy('<br>')}
   else if((dsels+sels).indexOf(gizmos[i]) != -1){makeSelect(gizmos[i])}
   else if((dbuts+buts+tbuts).indexOf(gizmos[i]) != -1){makeButton(gizmos[i])}
  }
 }
 writeToWhizzy(t_end)//table controls end
 writeToWhizzy('<a href="http://www.unverse.net" style="color:buttonface" title="'+whizzywig_version+'">.</a> ');
 writeToWhizzy(fGo('LINK'));
 if (linkBrowse){writeToWhizzy('<input type="button" onclick=doWin("'+linkBrowse+'"); value="'+whizzyTranslate("Browse")+'"> ')}
 writeToWhizzy(whizzyTranslate('Link address (URL)')+': <input type="text" id="lf_url'+idTa+'" size="60"><br><input type="button" value="http://" onclick="whizzyGetId(\'lf_url'+idTa+'\').value=\'http://\'+whizzyGetId(\'lf_url'+idTa+'\').value"> <input type="button" value="mailto:" onclick="whizzyGetId(\'lf_url'+idTa+'\').value=\'mailto:\'+whizzyGetId(\'lf_url'+idTa+'\').value"><input type="checkbox" id="lf_new'+idTa+'">'+whizzyTranslate("Open link in new window")+fNo(whizzyTranslate("OK"),"insertLink()"));//LINK_FORM end
 writeToWhizzy(fGo('IMAGE'));
 if(imageBrowse){writeToWhizzy('<input type="button" onclick=doWin("'+imageBrowse+'"); value="'+whizzyTranslate("Browse")+'"> ')}
 writeToWhizzy(whizzyTranslate('Image address (URL)')+': <input type="text" id="if_url'+idTa+'" size="50"> <label title='+whizzyTranslate("to display if image unavailable")+'><br>'+whizzyTranslate("Alternate text")+':<input id="if_alt'+idTa+'" type="text" size="50"></label><br>'+whizzyTranslate("Align")+':<select id="if_side'+idTa+'"><option value="none">_&hearts;_ '+whizzyTranslate("normal")+'</option><option value="left">&hearts;= &nbsp;'+whizzyTranslate("left")+'</option><option value="right">=&hearts; &nbsp;'+whizzyTranslate("right")+'</option></select> '+whizzyTranslate("Border")+':<input type="text" id="if_border'+idTa+'" size="20" value="0" title="'+whizzyTranslate("number or CSS e.g. 3px maroon outset")+'"> '+whizzyTranslate("Margin")+':<input type="text" id="if_margin'+idTa+'" size="20" value="0" title="'+whizzyTranslate("number or CSS e.g. 5px 1em")+'">'+fNo(whizzyTranslate("Insert Image"),"insertImage()"));//IMAGE_FORM end
 writeToWhizzy(fGo('TABLE')+whizzyTranslate("Rows")+':<input type="text" id="tf_rows'+idTa+'" size="2" value="3"> <select id="tf_head'+idTa+'"><option value="0">'+whizzyTranslate("No header row")+'</option><option value="1">'+whizzyTranslate("Include header row")+'</option></select> '+whizzyTranslate("Columns")+':<input type="text" id="tf_cols'+idTa+'" size="2" value="3"> '+whizzyTranslate("Border width")+':<input type="text" id="tf_border'+idTa+'" size="2" value="1"> '+fNo(whizzyTranslate("Insert Table"),"makeTable()"));//TABLE_FORM end
 writeToWhizzy(fGo('COLOR')+'<input type="hidden" id="cf_cmd'+idTa+'"><div style="background:#000;padding:1px;height:22px;width:125px;float:left"><div id="cPrvw'+idTa+'" style="background-color:red; height:100%; width:100%"></div></div> <input type=text id="cf_color'+idTa+'" value="red" size=17 onpaste=vC(value) onblur=vC(value)> <input type="button" onmouseover=vC() onclick=sC() value="'+whizzyTranslate("OK")+'">  <input type="button" onclick="hideDialogs();" value="'+whizzyTranslate("Cancel")+'"><br> '+whizzyTranslate("click below or enter a")+' <a href="http://www.unverse.net/colortable.htm" target="_blank">'+whizzyTranslate("color name")+'</a><br clear=all> <table border=0 cellspacing=1 cellpadding=0 width=480 bgcolor="#000000">'+"\n");
 var wC=new Array("00","33","66","99","CC","FF")  //color table
 for (i=0; i<wC.length; i++){
  writeToWhizzy("<tr>");
  for (j=0; j<wC.length; j++){
   for (k=0; k<wC.length; k++){
    var clr=wC[i]+wC[j]+wC[k];
    writeToWhizzy(' <td style="background:#'+clr+';height:12px;width:12px" onmouseover=vC("#'+clr+'") onclick=sC("#'+clr+'")></td>'+"\n");
   }
  }
  writeToWhizzy('</tr>');
 }
 writeToWhizzy("</table></div>\n"); //end color table,COLOR_FORM
 writeToWhizzy("</div>\n"); //controls end
 writeToWhizzy('<div class="wzCtrl" id="showWYSIWYG'+idTa+'" style="display:none"><input type="button" onclick="showDesign();" value="'+whizzyTranslate("Hide HTML")+'">');
 tagButs();
 writeToWhizzy('</div>'+"\n");
 writeToWhizzy('<iframe style="border:0px; width:99.6%; height:'+taHeight+'" src="javascript:;" id="whizzy'+idTa+'"></iframe></div>'+"\n", true); //finally write content to whizzy container
 var startHTML="<html>\n<head>\n<style>td,th{border:1px dotted #888}</style>\n";
 if(cssFile){startHTML += '<link media="all" type="text/css" href="'+cssFile+'" rel="stylesheet">\n'}
 if(jsFile){startHTML += '<script type="text/javascript" src="' + jsFile + '"></script>\n'} 
 startHTML += '</head>\n<body id="'+idTa+'" style="background-image:none;font-family: Georgia,"Times New Roman","Bitstream Charter",Times,serif;" >\n'+tidyD(taContent)+'</body>\n</html>';
 oW=whizzyGetId("whizzy"+idTa).contentWindow;
 var d=oW.document;
 try{d.designMode="on";} catch(e){ setTimeout('oW.designMode="on";', 100);}
 d.open(); d.write(startHTML); d.close();
 if(oW.addEventListener){oW.addEventListener("keypress", kb_handler, true)}//keyboard shortcuts for Moz
 else{d.body.attachEvent("onpaste",function(){setTimeout('cleanUp()',10)})}
 addEvt(d,"mouseup", whereAmI); addEvt(d,"keyup", whereAmI); addEvt(d,"dblclick", doDbl); 
 //addEvt(d,"click", clickWhizzywig);
 //move textarea so html menu appears on top
 taObject = whizzyGetId(idTa);
 whizzyGetId("CONTAINER"+idTa).removeChild(whizzyGetId(idTa));
 whizzyGetId("CONTAINER"+idTa).appendChild(taObject);
 //end move
 idTa=null;
} //end makeWhizzyWig
function whizzywig(controls){
 var i,ta=document.getElementsByTagName('TEXTAREA');
 for (i=0;i<ta.length;i++){
  if(!ta[i].id){ta[i].id=ta.name}
  makeWhizzyWig(ta[i].id,controls);
 }
}
//function clickWhizzywig(p){parent.currently_focus_even(idTa)}
function addEvt(o,e,f){if(wn.addEventListener){o.addEventListener(e, f, false)}else{o.attachEvent("on"+e,f)}}
function doDbl(){if(papa.nodeName == 'IMG'){doImage()}else{if(papa.nodeName=='A'){doLink()}}}
function makeButton(button){// assemble the button requested
 var butHTML, ucBut=button.substring(0,1).toUpperCase();
 ucBut += button.substring(1);
 ucBut=whizzyTranslate(ucBut.replace(/_/g,' '));
 if(!document.frames && (button=="spellcheck")){return}//Not allowed from Firefox
 if(whizzyGetId(idTa).nodeName!="TEXTAREA" && button=="html"){return}
 if(!buttonExt){buttonExt='.gif'}
 if (buttonPath == "textbuttons"){butHTML='<button type=button onClick=makeSo("'+button+'")>'+ucBut+"</button>\n"}
 else{butHTML='<button  title="'+ucBut+'" type=button onClick=makeSo("'+button+'")>'+(btn[button]!=undefined?'<div style="width:'+btn._w+'px;height:'+btn._h+'px;background-image:url('+btn._f+');background-position:-'+btn[button]+'px 0px"></div>':'<img src="'+buttonPath+button+buttonExt+'" alt="'+ucBut+'" onError="this.parentNode.innerHTML=this.alt">')+'</button>\n'}
 writeToWhizzy(butHTML)
}
function fGo(id){return '<div id="'+id+'_FORM'+idTa+'" unselectable="on" style="display:none" onkeypress="if(event.keyCode==13) {return false;}"><hr>'+"\n"}//new form
function fNo(txt,go){//form do it/cancel buttons
 return ' <input type="button" onclick="'+go+'" value="'+txt+'"> <input type="button" onclick="hideDialogs();" value='+whizzyTranslate("Cancel")+"></div>\n";
}
function makeSelect(select){//assemble the <select> requested
 var values,options,h,i;
 if (select == 'formatblock'){
 h="Heading";
 values=["<p>", "<p>", "<h1>", "<h2>", "<h3>", "<h4>", "<h5>", "<h6>", "<address>",  "<pre>"];
 options=[whizzyTranslate("Choose style")+":", whizzyTranslate("Paragraph"), whizzyTranslate(h)+" 1 ", whizzyTranslate(h)+" 2 ", whizzyTranslate(h)+" 3 ", whizzyTranslate(h)+" 4 ", whizzyTranslate(h)+" 5 ", whizzyTranslate(h)+" 6", whizzyTranslate("Address"), whizzyTranslate("Fixed width<pre>")];
 }else if (select == 'fontname') {
  values=["Arial, Helvetica, sans-serif", "Arial, Helvetica, sans-serif","'Arial Black', Helvetica, sans-serif", "'Comic Sans MS' fantasy", "Courier New, Courier, monospace", "Georgia, serif", "Impact,sans-serif","'Times New Roman', Times, serif", "'Trebuchet MS',sans-serif", "Verdana, Arial, Helvetica, sans-serif"];
  options=[whizzyTranslate("Font")+":", "Arial","Arial Black", "Comic", "Courier", "Georgia", "Impact","Times New Roman", "Trebuchet","Verdana"]
 }else if(select == 'fontsize'){
  values=["3", "1", "2", "3", "4", "5", "6", "7"];
  options=[whizzyTranslate("Font size")+":", "1 "+whizzyTranslate("Small"), "2", "3", "4", "5", "6", "7 "+whizzyTranslate("Big")]
 }else{ 
  values=vals[select];
  options=opts[select]
 }
 writeToWhizzy('<select id="'+select+idTa+'" onchange="doSelect(this.id);">'+"\n");
 for (i=0;i<values.length;i++){writeToWhizzy(' <option value="' + values[i] + '">' + options[i] + "</option>\n")}
 writeToWhizzy("</select>\n")
}
function tagButs(){
 writeToWhizzy('<input type="button" onclick=\'doTag("<h1>")\' value="H1" title="<H1>"><input type="button" onclick=\'doTag("<h2>")\' value="H2" title="<H2>"><input type="button" onclick=\'doTag("<h3>")\' value="H3" title="<H3>"><input type="button" onclick=\'doTag("<h4>")\' value="H4" title="<H4>"><input type="button" onclick=\'doTag("<p>")\' value="P" title="<P>"><input type="button" onclick=\'doTag("<strong>")\' value="S" title="<STRONG>" style="font-weight:bold"><input type="button" onclick=\'doTag("<em>")\' value="E" title="<EM>" style="font-style:italic;"><input type="button" onclick=\'doTag("<li>")\' value="&bull;&mdash;" title="<LI>"><input type="button" onclick=\'doTag("<a>")\' value="@" title="<A HREF= >"><input type="button" onclick=\'doTag("<img>")\' value="[&hearts;]" title="<IMG SRC= >"><input type="button" onclick=\'doTag("<br />")\' value="&larr;" title="<BR />">');
}
function xC(c,o){return oW.document.execCommand(c,false,o)}
function makeSo(cm,op){//format selected text or line in the whizzy
 hideDialogs();
 oW.focus();
 if(dobut[cm]) {insHTML(dobut[cm]); return;}
 if (/Firefox/.test(navigator.userAgent)) {xC("styleWithCSS",cm=="hilite")} //no spans for bold, italic, ok hilite
 if(cm=="justify"){cm="full"}
 if("leftrightcenterfull".indexOf(cm)!=-1){cm="justify"+cm}
 else if(cm=="number"){cm="insertorderedlist"}
 else if(cm=="bullet"){cm="insertunorderedlist"}
 else if (cm=="rule"){cm="inserthorizontalrule"}
 switch(cm){
  case "color":whizzyGetId('cf_cmd'+idTa).value="forecolor"; if(textSel()){whizzyShowElement('COLOR_FORM'+idTa)} break;
  case "hilite":whizzyGetId('cf_cmd'+idTa).value=cm; if(textSel()){whizzyShowElement('COLOR_FORM'+idTa)} break;
  case "image":doImage(); break;
  case "link":doLink(); break;
  case "html":showHTML(); break;
  case "table":doTable(); break;
  case "delete_row":doRow('delete','0'); break;
  case "add_row_above":doRow('add','0'); break;
  case "add_row_below":doRow('add','1'); break;
  case "delete_column":doCol('delete','0'); break;
  case "add_column_before":doCol('add','0'); break;
  case "add_column_after":doCol('add','1'); break;
  case "table_in_cell":hideDialogs(); whizzyShowElement('TABLE_FORM'+idTa); break;
  case "clean":cleanUp(); break;
  case "spellcheck":spellCheck(); break;
  case "fullscreen":fullscreen(); break;
  default:xC(cm,op); break;
 }
 oW.focus();
}
function doSelect(selectname) {  //select on toolbar used - do it
 var idx=whizzyGetId(selectname).selectedIndex;
 var selected=whizzyGetId(selectname).options[idx].value;
 whizzyGetId(selectname).selectedIndex=0;
 selectname=selectname.replace(idTa,"");
 if (" _formatblock_fontname_fontsize".indexOf('_'+selectname) > 0) {
  var cmd=selectname;
  oW.focus();
  xC(cmd,selected);
 } else {
  insHTML(selected);
 }  
 oW.focus();
}
function vC(colour){// view Color
 if(!colour){colour=whizzyGetId('cf_color'+idTa).value}
 whizzyGetId('cPrvw'+idTa).style.backgroundColor=colour;
 whizzyGetId('cf_color'+idTa).value=colour
}
function sC(color) {  //set Color 
 hideDialogs();
 var cmd=whizzyGetId('cf_cmd'+idTa).value;
 if(!color){color=whizzyGetId('cf_color'+idTa).value}
 if(rng){rng.select();}
 if(cmd=="hilite"){try{xC("hilitecolor",color)}catch(e){xC("backcolor",color)}}
 else{xC(cmd,color)}
 oW.focus();
}
function doLink(){
 if(textSel()){
  if(papa.nodeName=='A'){whizzyGetId("lf_url"+idTa).value=papa.href}
  whizzyShowElement('LINK_FORM'+idTa)
 }
}
function insertLink(url) {
 if (rng){rng.select()}
 var a,i,mk='http://whizzy.wig/mark',
 URL=url ? url : whizzyGetId("lf_url"+idTa).value; 
 if (URL.replace(/ /g,"")===""){xC('Unlink',null)}else{
  xC('CreateLink',mk);
  a=oW.document.body.getElementsByTagName("A");
  for (i=0;i<a.length;i++){
   if (a[i].href==mk){a[i].href=URL; if(whizzyGetId("lf_new"+idTa).checked){a[i].target="_blank"}break}
  }
 }
 hideDialogs();
}
function doImage(){
 if (papa && papa.nodeName == 'IMG'){
  whizzyGetId("if_url"+idTa).value=papa.src;
  whizzyGetId("if_alt"+idTa).value=papa.alt;
  var position = papa.style.cssFloat?papa.style.cssFloat:papa.style.styleFloat;
  whizzyGetId("if_side"+idTa).selectedIndex=(position=="left")?1:(position=="right")?2:0; 
  whizzyGetId("if_border"+idTa).value=papa.style.border?papa.style.border:papa.border>0?papa.border:0;
  whizzyGetId("if_margin"+idTa).value=papa.style.margin?papa.style.margin:papa.hspace>0?papa.hspace:0;
 }
 whizzyShowElement('IMAGE_FORM'+idTa);
}
function insertImage(URL, side, border, margin, alt) { // insert image as specified
 hideDialogs();
 if(!URL){URL=whizzyGetId("if_url"+idTa).value}
 if (URL) {
  if (!alt){alt=whizzyGetId("if_alt"+idTa).value ? whizzyGetId("if_alt"+idTa).value: URL.replace(/.*\/(.+)\..*/,"$1")}
  img='<img alt="' + alt + '" src="' + URL +'" ';
  if(!side){side=whizzyGetId("if_side"+idTa).value}
  if((side=="left") || (side=="right")){align='float:'+side+';'}else{align=''}
  if(!border){border=whizzyGetId("if_border"+idTa).value}
  if(border.match(/^\d+$/)){border+='px solid'}
  if(!margin){margin=whizzyGetId("if_margin"+idTa).value}
  if(margin.match(/^\d+$/)){margin+='px'}
  if(border || margin){img+=' style="border:'+border+';margin:'+margin+';'+align+ '"'}
  img+='/>';
  insHTML(img)
 }
}
function doTable(){ //show table controls if in a table, else make table
 if(trail && trail.indexOf('TABLE') > 0){whizzyShowElement('TABLE_CONTROLS'+idTa)}
  else{whizzyShowElement('TABLE_FORM'+idTa)}
}
function doRow(toDo,below) { //insert or delete a table row
 var pa=papa,tRow,tCols,newRow,newCell;
 while(pa.tagName != "TR"){pa=pa.parentNode}
 tRow=pa.rowIndex;
 tCols=pa.cells.length;
 while(pa.tagName != "TABLE"){pa=pa.parentNode}
 if(toDo=="delete"){pa.deleteRow(tRow)}
 else{
  newRow=pa.insertRow(tRow+parseInt(below,10));//1=below 0=above
   for(i=0;i<tCols;i++){
    newCell=newRow.insertCell(i);
    newCell.innerHTML="#";
   }
 }
}
function doCol(toDo,after) {//insert or delete a column
 var pa=papa,tCol,tRows,i,newCell;
 while(pa.tagName != 'TD'){pa=pa.parentNode}
 tCol=pa.cellIndex;
 while(pa.tagName != "TABLE"){pa=pa.parentNode}
 tRows=pa.rows.length;
 for(i=0;i<tRows;i++){
  if(toDo=="delete"){pa.rows[i].deleteCell(tCol)}
  else{
   newCell=pa.rows[i].insertCell(tCol+parseInt(after,10));//if after=0 then before
   newCell.innerHTML="#";
  }
 }
}
function makeTable() { //insert a table
 hideDialogs();
 var rows=whizzyGetId('tf_rows'+idTa).value, cols=whizzyGetId('tf_cols'+idTa).value, border=whizzyGetId('tf_border'+idTa).value, head=whizzyGetId('tf_head'+idTa).value, table,i,j;
 if ((rows>0)&&(cols>0)){
  table='<table border="'+border+'">';
  for (i=1;i<=rows;i++){
   table=table+"<tr>";
   for (j=1;j<=cols;j++){
    if (i==1){
     if(head=="1"){table += "<th>Title"+j+"</th>"}//Title1 Title2 etc.
     else{table+="<td>"+j+"</td>"}
    }
    else if(j==1){table+="<td>"+i+"</td>"}
   else{table += "<td>#</td>"}
   }
   table+ "</tr>";
  }
  table+=" </table>";
  insHTML(table)
 }
}
function doWin(URL) {  //popup  for browse function
 ppw=wn.open(URL,'popWhizz','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=640,height=480,top=100');
 ppw.focus();
}
function spellCheck(){//check spelling with ieSpell if available
 try {
  var axo=new ActiveXObject("ieSpell.ieSpellExtension");
  axo.CheckAllLinkedDocuments(document);
 } catch(e) {
  if(e.number==-2146827859) {
  if(confirm("ieSpell is not installed on your computer. \n Click [OK] to go to download page."))
   {wn.open("http://www.iespell.com/download.php","DownLoad")}
  }else{
   alert("Error Loading ieSpell: Exception " + e.number)
  }
 }
}
function getWinSize(){//Returns window width,height
 var winW=window.innerWidth?window.innerWidth:document.documentElement.clientWidth?document.documentElement.clientWidth:document.body.clientWidth;
 var winH=window.innerHeight?window.innerHeight:document.documentElement.clientHeight?document.documentElement.clientHeight:document.body.clientHeight;
 return {w:winW,h:winH}
}
function fullscreen() {  //Enable or disable fullscreen
 var container=whizzyGetId("CONTAINER"+idTa);
 if(!isFullscreen()) {
  if(document.body.style.overflow){document.body.style.overflow="hidden";}else{document.documentElement.style.overflow="hidden";}
  document.body.style.visibility="hidden";
  container.style.visibility="visible"
  container.style.position="absolute";
  container.style.top=(window.pageYOffset?window.pageYOffset:document.body.scrollTop?document.body.scrollTop:document.documentElement.scrollTop) + "px";
  container.style.left="0";
  container.style.width=getWinSize().w+"px";
  container.style.height=getWinSize().h+"px";
  whizzyGetId("whizzy"+idTa).style.backgroundColor="#fff";
  whizzyGetId("whizzy"+idTa).style.height=getWinSize().h-whizzyGetId("CONTROLS"+idTa).offsetHeight+"px";
 }
 else {
  if(document.body.style.overflow){document.body.style.overflow="visible";}else{document.documentElement.style.overflow="";}
  document.body.style.visibility="visible";
  container.style.position="relative";
  container.style.top=whizzyGetId(idTa).style.top?whizzyGetId(idTa).style.top:"0px";
  container.style.left=whizzyGetId(idTa).style.left?whizzyGetId(idTa).style.left:"0px";
  container.style.width=taOrigSize[idTa].w;
  container.style.height="auto";
  whizzyGetId("whizzy"+idTa).style.backgroundColor="transparent";
  whizzyGetId("whizzy"+idTa).style.height=taOrigSize[idTa].h;
 }
}
function getDir() { //Detect current whizzywig directory
 var base=location.href,e=document.getElementsByTagName('base'),i;
 for(i=0;i<e.length;i++){
  if(e[i].href){base = e[i].href}
 }
 e=document.getElementsByTagName('script');
 for(i=0;i<e.length;i++) {
  if(e[i].src && /(^|\/)whizzywig\.js([?#].*)?$/i.test(e[i].src)){
   return e[i].src.replace(/whizzywig\.js/i.exec(e[i].src),'')
  }
  else if(e[i].src && /(^|\/)whizzywig[^\/].*?\.js([?#].*)?$/i.test(e[i].src)){
   return e[i].src.replace(/whizzywig[^\/].*?\.js/i.exec(e[i].src), '')
  }
 }
 return '';
}
function isFullscreen(){ //Check if whizzywig is on fullscreen mode
 if(whizzyGetId("CONTAINER"+idTa).style.width==getWinSize().w+"px"){return true}
 return false
}
function hideDialogs() {
 whizzyHideElement('LINK_FORM'+idTa); whizzyHideElement('IMAGE_FORM'+idTa); whizzyHideElement('COLOR_FORM'+idTa); whizzyHideElement('TABLE_FORM'+idTa); whizzyHideElement('TABLE_CONTROLS'+idTa);
}
function showDesign(){
 oW.document.body.innerHTML=tidyD(whizzyGetId(idTa).value);
 whizzyHideElement(idTa); whizzyHideElement('showWYSIWYG'+idTa); whizzyShowElement('CONTROLS'+idTa); whizzyShowElement('whizzy'+idTa);
 if(whizzyGetId("whizzy"+idTa).contentDocument){whizzyGetId("whizzy"+idTa).contentDocument.designMode="on"}//FF loses it on hide
 oW.focus()
}
function showHTML(){
 whizzyGetId(idTa).value=tidyH(oW.document);
 whizzyHideElement('CONTROLS'+idTa); whizzyHideElement('whizzy'+idTa); whizzyShowElement(idTa); whizzyShowElement('showWYSIWYG'+idTa);
 if(isFullscreen()){
  whizzyGetId(idTa).style.width=getWinSize().w+"px";
  whizzyGetId(idTa).style.height=(getWinSize().h-whizzyGetId('showWYSIWYG'+idTa).offsetHeight)+"px";
  whizzyGetId(idTa).style.borderWidth = "0px"
 }else{
  whizzyGetId(idTa).style.position="relative";
  whizzyGetId(idTa).style.width=taOrigSize[idTa].w;
  whizzyGetId(idTa).style.height=taOrigSize[idTa].h;
  whizzyGetId(idTa).style.borderWidth="1px"
 }
 whizzyGetId(idTa).focus()
}

function syncTextarea(){//tidy up before we go-go
 for (var i=0;i<whizzies.length;i++){
  var t=whizzies[i];
  var d=whizzyGetId("whizzy"+t).contentWindow.document;
  if (whizzyGetId(t).style.display=='block'){d.body.innerHTML=whizzyGetId(t).value}
  var ret=(whizzyGetId(t).nodeName!="TEXTAREA") ? whizzyGetId('wzhid_'+whizzyGetId(t).id) : whizzyGetId(t);
  ret.value=tidyH(d)
 }
}
function cleanUp(){xC("removeformat",null); tidyH(oW.document)}
function tidyD(h){//FF designmode likes <B>,<I>...
 h=h.replace(/<(\/?)strong([^>]*)>/gi,"<$1B$2>").replace(/<(\/?)em>/gi,"<$1I>");
 return h
}
function tidyH(d){//attempt valid xhtml
 function lc(str){return str.toLowerCase()}
 function qa(str){return str.replace(/(\s+\w+=)([^"][^>\s]*)/gi,'$1"$2"');}
 function sa(str){return str.replace(/("|;)\s*[A-Z-]+\s*:/g,lc);}
 var sz=['medium','xx-small','x-small','small','medium','large','x-large','xx-large'],
 fs=d.getElementsByTagName("FONT"),i,ih;
 for (i=0;i<fs.length;i++){
  if (fs[i].face) {fs[i].style.fontFamily = fs[i].face; fs[i].removeAttribute('face')}
  if (fs[i].size) {fs[i].style.fontSize = sz[fs[i].size]; fs[i].removeAttribute('size')} 
  if (fs[i].color) {fs[i].style.color = fs[i].color; fs[i].removeAttribute('color')}
 }
 ih=d.body.innerHTML;
 ih=ih.replace(/(<\/?)FONT([^>]*)/gi,"$1span$2") 
 .replace(/(<\/?)[B](\s+[^>]*)?>/gi, "$1strong$2>")
 .replace(/(<\/?)[I](\s+[^>]*)?>/gi, "$1em$2>")
 .replace(/<\/?(COL|XML|ST1|SHAPE|V:|O:|F:|F |PATH|LOCK|IMAGEDATA|STROKE|FORMULAS)[^>]*>/gi, "")
 .replace(/\bCLASS="?(MSOw*|Apple-style-span)"?/gi,"")
 .replace(/<[^>]+=[^>]+>/g,qa) //quote all atts
 .replace(/<(TABLE|TD|TH|COL)(.*)(WIDTH|HEIGHT)=["'0-9A-Z]*/gi, "<$1$2")//no fixed size tables (%OK) [^A-Za-z>]
 .replace(/<([^>]+)>\s*<\/\1>/g, "")//empty tag
 .replace(/><(H|P|D|T|BLO|FOR|IN|SE|OP|UL|OL|LI|SC)/gi,">\n<$1")//newline adjacent blocks
 .replace(/(<BR ?\/?>)([^\r\n])/gi,"$1\n$2")//newline on BR
 .replace(/([^\n])<(P|DIV|TAB|FOR)/gi,"$1\n\n<$2") //add white space
 .replace(/([^\n])<\/(UL|OL|DL|DIV|TAB|FOR)/gi,"$1\n</$2") //end block
 .replace(/([^\n])(<\/TR)/gi,"$1\n $2") //end row
 .replace(/\n<(BLO|LI|OP|TR|IN|DT)/gi,"\n <$1") //indent..
 .replace(/\n<(TD|TH|DD)/gi,"\n  <$1") //..more
 .replace(window.location.href+'#','#') //IE anchor bug
 .replace(/<(IMG|INPUT|BR|HR|LINK|META)([^>]*)>/gi,"<$1$2 />") //self-close tags
 .replace(/(<\/?[A-Z]*)/g,lc) //lowercase tags...
 .replace(/STYLE="[^"]*"/gi,sa); //lc style atts
 return ih
}
function kb_handler(e){//keyboard controls for Moz
 var cmd=false, prm=false,k;
 if(e && (e.ctrlKey && e.keyCode==e.DOM_VK_V)||(e.shiftKey && e.keyCode==e.DOM_VK_INSERT))
  {setTimeout('cleanUp()',10)}
 else if(e && e.keyCode==13 && !e.shiftKey &&papa.nodeName=="BODY"){cmd="formatblock"; prm="<p>"}
 else if(e && e.ctrlKey){
  k=String.fromCharCode(e.charCode).toLowerCase();
  cmd=(k=='b')?'bold':(k=='i')?'italic':(k=='l')?'link':(k=='m')?'image':false;
 }
 if(cmd){
  makeSo(cmd,prm);
  e.preventDefault();//stop event bubble
  e.stopPropagation()
 }
}
function doTag(html){//insert HTML into textarea
 var url,close='',before,after;
 if(!html){html=prompt("Enter some HTML or text to insert:", "")}
 whizzyGetId(idTa).focus();
 if(html=='<a>'){
  url=prompt("Link address:","http://"); 
  html='<a href="'+url+'">'
 }
 if(html=='<img>'){
  url=prompt("Address of image:","http://"); 
  var alt=prompt("Description of image");
  html ='<img src="'+url+'" alt="'+alt+'">';
 }
 if(html.indexOf('<')===0 && html.indexOf('br') != 1 && html.indexOf('img') != 1)
  {close=html.replace(/<([a-z0-6]+).*/,"<\/$1>")}
 if(html != '<strong>' && html != '<em>'){close+='\n'}
 if (document.selection){
  sel=document.selection.createRange();
  sel.text=html+sel.text+close
 }else{
   before=whizzyGetId(idTa).value.slice(0,whizzyGetId(idTa).selectionStart);
   sel=whizzyGetId(idTa).value.slice(whizzyGetId(idTa).selectionStart,whizzyGetId(idTa).selectionEnd);
   after=whizzyGetId(idTa).value.slice(whizzyGetId(idTa).selectionEnd);
   whizzyGetId(idTa).value =before+html+sel+close+after
 }
 whizzyGetId(idTa).focus()
}
function insHTML(html){//insert HTML at current selection
 if(!html){html=prompt(whizzyTranslate("Enter some HTML or text to insert:"), "")}
 if(html.indexOf('js:')===0){
  try{eval(html.replace(/^js:/,''))} catch(e){}
  return
 }
 whereAmI();
 try {xC("inserthtml",html+sel)}
 catch(e){if (document.selection) {
  if(papa&&papa.nodeName=='IMG'){papa.outerHTML=html+papa.outerHTML;}
  else if(rng){rng.select(); rng.pasteHTML(html+rng.htmlText)}
 } }
}
function whereAmI(e){
 if(!e){e=wn.event}
 var mu=e&&e.type=='mouseup',pa,id;
 if (msIE){//Issue 11
  oW.document.getElementsByTagName("body")[0].focus(); 
  sel=oW.document.selection;
  rng=sel.createRange();
  papa=mu?e.srcElement:(sel.type == "Control")?rng.item(0):rng.parentElement();
 }else{
  sel=oW.getSelection();
  
  // FIXED: if sel is null then controls are not working..!!
  if(sel == null) {
      sel = '';
  }
        sn=sel.anchorNode;
  papa=mu?e.target:(sn.nodeName == '#text')?sn.parentNode:sn;
 }
 pa=papa;
 trail=papa.nodeName; 
 while(!pa.nodeName.match(/^(HTML|BODY)/) && pa.className!="wzCtrl"){
  pa=pa.parentNode;
  trail=pa.nodeName+'>'+trail
 }
 if(pa.className=="wzCtrl"){trail=sel=rng=null}
 id=pa.nodeName=="HTML" ? pa.getElementsByTagName("BODY")[0].id : pa.id.replace("CONTROL","");
 setCurrentWhizzy(id); 
 wn.status=id+":"+trail;
 if(trail.indexOf('TABLE')>0){whizzyShowElement('TABLE_CONTROLS'+idTa)}else{whizzyHideElement('TABLE_CONTROLS'+idTa)}
}
function setCurrentWhizzy(id){//set current whizzy
 if(id==="" || whizzies.join().indexOf(id)=='-1'){return}
 if (id!=idTa){
  idTa=id;
  try {oW=whizzyGetId("whizzy"+id).contentWindow;} catch(e){alert('set current: '+id)}
  if(oW){if(oW.focus){oW.focus()}wn.status=oW.document.body.id}
 }
} 
function writeToWhizzy(str,finalize){//write to whizzy container
 if(!writeToWhizzy.temp){writeToWhizzy.temp=""}
 writeToWhizzy.temp+=str;
 if(finalize){
  whizzyGetId("CONTAINER"+idTa).innerHTML+=writeToWhizzy.temp;
  writeToWhizzy.temp=""
 }
} 
function textSel(){if(sel && sel.type != "None" && sel.type != "Caret"){return true}else{alert(whizzyTranslate("Select some text first")); return false}}
function whizzyShowElement(id){whizzyGetId(id).style.display='block'}//show element
function whizzyHideElement(id){whizzyGetId(id).style.display='none'}//hide element
function whizzyGetId(id){return document.getElementById(id)}//get element by ID
function whizzyTranslate(key){return (wn.language && language[key]) ? language[key] :  key;}//translation