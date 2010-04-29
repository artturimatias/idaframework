/**
* @fileoverview This file provides base gui-functions for IDA-framework
*
* @author Ari H&auml;yrinen
* @version 0.1
*/

var DEBUG = 0;  // alerts xml

// keycodes
var TAB = 9;
var RETURN = 13;
var ESC = 27;
var OFFSET = 10;
var TRUE = 1;
var FALSE = 0;
d = document;

// structured XML
DATA = 0;
EVENTS = 1;
ACTIONS = 8;
PARTICIPANTS = 20;
PARTS = 2;
LINKS = 3;
DOCUMENTS = 4;
NOTES = 5;
IMAGES = 6;
FILES = 7;

POSSIBLE_EVENTS = 1;
POSSIBLE_PARTS = 2;


var ajaxHandlers = new Array();
var links = new Array();

var current_div;
var main;           // this is our "root", the target div
var open_edit;
var target;
var listAll;



if (typeof(IDA) == "undefined") {
    IDA = {}
} else {
    IDA = this;
}


IDA.serverPath = "../idacore/server/";
IDA.winCount = 1;


/**
* @namespace IDA.gui
*/
IDA.gui = {};


IDA.gui.openLogin = function (userCallBack) {



    IDA.gui.userCall = userCallBack;
    $("body").append("<div id='idaloginholder'><div>User Name</div><input id='idauser'><div>Password</div><input id='idapass' type='password'><div><input id='idalogin_submit' value='login' type='submit' /></div></div>");
    $("#idalogin_submit").click(function() {IDA.gui.startLogin(); });
    $("#idaloginholder").dialog({modal:true, title: 'login', position:['center','center'], close:function(ev,ui) {$(this).remove()}});

    // get one-time seed
    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<loginseed>\n</loginseed>";

    var p = function (xml) { IDA.gui.loginSeedCallback(xml); };
    var e = function (error) { alert(error); };
    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p,e);


}


IDA.gui.logout = function (userCallBack) {

    IDA.gui.userCall = userCallBack;
    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<logout>\n</logout>\n";

    // send logout
    var p = function (xml) { IDA.gui.logoutCallback(xml); };
    var e = function (error) { alert(error); };
    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p,e);

}


/**
    * Fetch and display record template
    * @param {String}class record class for example "Person"
    * @param {String|DOMnode}divName id of div element or DOM-node where to put display
*/
IDA.gui.openTemplate = function (class,divName, callBack) {

    this.browser = new IDA.browser(divName);
    this.browser.loadTemplate(class, callBack);
}


IDA.gui.classInfo = function (class, divName) {

    // check if this is dom node or node name
    var domNode = IDA.gui.getDiv(divName);
    IDA.gui.cleanDiv(domNode);

    var p = function (req) { IDA.gui.classInfoCallBack(req, domNode) };
   // var e = function (status) { alert("AJAX error: "+status); };

    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<gettemplate title=\"" + class + "\" />\n";

    var ajax = new IDA.Ajax;
    ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p);

}


IDA.gui.showXML = function (div, tag) {

        var a = tag.attributes,
        attrs = Array();
        for (i=0; i < a.length; i++) {
             attrs.push(a[i].nodeName + '="' + a[i].nodeValue + '"');
         } 

        var n = attrs.join(" ");
                    
        $(div).append("&lt;<span>"+ tag.tagName + "</span> "+n+" &gt;" );  
        if(tag.firstChild)
            if(tag.firstChild.nodeValue != null)
                $(div).append($("<span>" + tag.firstChild.nodeValue + "</span>").css({'color':'orange'}));  

        $(tag).children().each(function() {

            var next = $("<div>");
            IDA.gui.showXML(next, this);
            $(div).append(next);

        });

        $(div).append("&lt;/<span>"+ tag.tagName + "</span> &gt;<br>" );  

}


IDA.gui.addSaveCancelBtns = function (pointer, div) {

    var cont = IDA.gui.createElement("div",{className:"save_container"});
    var saveBtn = IDA.gui.createElement("a",{className:"save",href:"#"}, "save");
    var cancelBtn = IDA.gui.createElement("a",{className:"cancel",href:"#"}, "cancel");

    saveBtn.onclick = function () {pointer.save();return false };
    cancelBtn.onclick = function () {pointer.cancel(); return false };

    cont.appendChild(saveBtn);
    cont.appendChild(cancelBtn);

    if(div)
        div.appendChild(cont);
    else
        pointer.div.appendChild(cont);
}

/**
    * Opens input for file upload
    * @param {String|DOMnode}divName id of div element or DOM-node where to put display
*/
IDA.gui.openFileUpload = function (divName) {

    // check if this is dom node or node name
    var div = IDA.gui.getDiv(divName);

    if(div) {

        IDA.gui.cleanDiv(div);
        IDA.gui.toggleList("none");

        var holder = IDA.gui.createElement("div",{"id":"ida_uploadholder"});
        var fileForm = IDA.gui.createElement("form", {method:"post", enctype:"multipart/form-data", action:IDA.serverPath + "upload.php"});
        var userFile = IDA.gui.createElement("input", {type:"file", name:"uploadfile"});
        var userSubmit = IDA.gui.createElement("input", {type:"submit"});
        var cancel = IDA.gui.createButton("cancel");
        cancel.onclick = function () { IDA.gui._removeParentNode(this); IDA.gui.toggleList("block");};
        userSubmit.onclick = function () {IDA.gui.checkForm(fileForm, userFile); return false;}

        fileForm.appendChild(userFile);
        fileForm.appendChild(userSubmit);
        fileForm.appendChild(cancel);
            
        holder.appendChild(fileForm);
        div.appendChild(holder);

    }
}



/**
    * @private
*/
IDA.gui._removeParentNode = function   (ele) {
        
    p = ele.parentNode;
    q = p.parentNode;
    q.removeChild(p);

}


IDA.gui._makeAjaxSelection = function (selVal, func) {

    var data_c = IDA.gui.createElement("div",{className:"selInstance"},selVal);
    var del = IDA.gui.createElement("a",{className:"delete", title:"remove"}, "X");
    
    if(func)
        del.onclick = function () { func(this); }
    else
        del.onclick = function () { IDA.gui._removeParentNode(this) };
        
    data_c.appendChild(del);
    return data_c;

}


/**
    * Open linkboard
    * @param {String|DOMnode}divName id of div element or DOM-node where to put display
*/
IDA.gui.showLinkBoard = function (divName, pos, top, up) {

    // check if this is dom node or node name
    var div = IDA.gui.getDiv(divName);
    IDA.gui.dragDrop.init("copy");
    var mode = "linkboard";

    if(div) {

    IDA.gui.cleanDiv(div);

    var holder = IDA.gui.createElement("div", {id:"idalinkboardholder"});
    var header = IDA.gui.createElement("div", {}, "linkboard");
        if(pos == "fixed")
            holder.style.position = "fixed";
        if(top)
            holder.style.top = top + "px";
        if(up == "files")
            mode = "uploads";
       

    holder.appendChild(header);
    div.appendChild(holder);

    // get uploaded files
    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<" + mode + ">\n</" + mode + ">";
    var p = function (xml) { IDA.gui.showLinkboardCallback(xml); };
    var e = function (error) { alert(error); };
    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p,e);

    }
}



IDA.gui.openQuickSearch = function (divName, browser) {

    var div = IDA.gui.getDiv(divName);

    if(div) {

        IDA.gui._createSearch(divName, browser);    

    }

}


IDA.gui.checkDiv = function (divName) {

    // check if this is dom node or node name
    if(!divName.tagName) {
    if(di = d.getElementById(divName))
        return di;
    else
        return false;
    } else
    return divName;
}




IDA.gui.getDiv = function (divName) {

    // check if this is dom node or node name
    if(!divName.tagName) {
        if(di = d.getElementById(divName))
            return di;
        else
            alert("Div \"" + divName + "\" not found!");
    } else
        return divName;
}

// ****************************************************************************
// END USER API ENDS HERE
// ****************************************************************************



IDA.gui._createSearch = function (divName, browser) {

    var div = IDA.gui.getDiv(divName);
//   script:IDA.serverPath + "xmlout.php?action=search&",
    var opts = {
    callback:function (id) {browser.load(id); },
    };

    var info = {
        c_name:"ida_search",
        size:"15"
    }

    var holder = IDA.gui.createElement("div", {});
    var input = IDA.gui.createTextInput(info);

    IDA.gui.cleanDiv(div);

    holder.appendChild(input);
    div.appendChild(holder);


    var as1 = new IDA.gui.quickSearch(input, opts);
}



// *************************************
// callbacks
// ************************************

/**
    * @private
*/
IDA.gui.loginSeedCallback = function (xml) {

    var node = xml.responseXML.getElementsByTagName("seed");
    var seed = node[0].firstChild.nodeValue;
    IDA.loginSeed = seed;

}

/**
    * @private
*/
IDA.gui.loginCallback = function (xml) {

    var res = xml.responseXML.getElementsByTagName("response");
    var val = res[0].firstChild.nodeValue;

    if(res[0].getAttribute("status") == "ok") {

        $("#idaloginholder").remove();

        //set cookie
        IDA.gui.setLogCookie(TRUE);


        if(IDA.gui.userCall) {
            IDA.gui.userCall();
        }

    } else {
        $("#idaloginholder").remove();
        alert(val);
    }
}

/**
    * @private
*/
IDA.gui.setLogCookie= function (state) {
    if(state == TRUE)
        document.cookie = "idalog=true; path=/";
    else
        document.cookie = "idalog=false; path=/";
}

// callback for class, type and table info
IDA.gui.classInfoCallBack = function (xml, domNode) {


    this.currTypeTitle = "";

    var root = xml.responseXML.getElementsByTagName("root")[0];
    var type = root.firstChild.tagName;
 
    $(domNode).append("<h2>"+type+"</h2>");
    
    $(root.firstChild).children().each(function () {
        if($(this).attr("class")) 
            var cl = $(this).attr("class")
        else
            var cl = "";


        $(domNode).append("<li>" + this.tagName + " " + cl + "</li>");

    });
}

// function readCookie is from http://www.quirksmode.org/js/cookies.html
/**
    * @private
*/
function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
    var c = ca[i];
    while (c.charAt(0)==' ') c = c.substring(1,c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

/**
    * @private
*/
IDA.gui.logoutCallback = function (xml) {

    var res = xml.responseXML.getElementsByTagName("response");

    if(res[0].getAttribute("status") == "ok") {

        IDA.gui.setLogCookie(FALSE);

        if(IDA.gui.userCall) {
            IDA.gui.userCall();
        }
    }
}



/**
    * @private
*/
IDA.gui.showLinkboardCallback = function (xml) {

    var h = d.getElementById("idalinkboardholder");
    var linkBoardContent = IDA.gui.createElement("div",{id:"linkBoardContent"});
    h.appendChild(linkBoardContent);

    var files = xml.responseXML.getElementsByTagName("file");

    for(var i=0; i < files.length; i++) {

    var imgDiv = IDA.gui.fileDiv(files[i],"");
    imgDiv.setAttribute("dragclass","dragdragbox");
    linkBoardContent.appendChild(imgDiv);

    }

    IDA.gui.dragDrop.CreateDragContainer(0, document.getElementById('linkBoardContent'));
}


// *************************************
// callbacks ends
// ************************************

/**
    * @private
*/
/**
    * @private
*/
IDA.gui.createTemplate = function (class, div) {

    this.browser = new IDA.browser(div);
    this.browser.loadTemplate(class);

}



/**
    * @private
*/
IDA.gui.checkForm = function (fileForm, file) {

    if(file.value == '') {
        alert("Select file first!");
    } else {
        fileForm.submit();
    }
}

/**
    * @private
*/
IDA.gui.openWin = function (classId, div) {
    
    var d = $(div).parents('div:eq(0)')[0];
    $(d).empty();
    IDA.gui.openTemplate(classId, d)
    //$(div.lastChild).dialog({ modal:true});
/*
    var win = window.open(".addnew.html?class="+classId+"", "ida_win" + id, "status=yes,width=500,height=500,scrollbars=yes");
*/
    
}



IDA.gui.listAll = function (classId, divName) {

    var domNode = IDA.gui.getDiv(divName);
    IDA.gui.cleanDiv(domNode); 
 
    var list = IDA.gui.createElement("div",{className:"divSel"});
    domNode.appendChild(list);
    $(list).dialog({title: classId}); 

    var e = function (error) { alert(error); };
    var p = function (req) { IDA.gui.listAllCallback(req, list); };
    //var e = function (status) { alert("AJAX error: "+status); };
    var xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<search><" + classId + "/><result is_identified_by=\"name\" /></search>";
    var ajax = new IDA.Ajax;
    ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p, e);

 
}


IDA.gui.listAllCallback = function (req, list) {
    
    var root = req.responseXML.firstChild;;
    $(root).children().each(function () {
        list.appendChild(IDA.gui.createElement("div",{className:"selList"},$(this).attr("name")));
    });
}

/**
    * @private
*/

/**
    * @private
*/

IDA.gui.quickSearch = function (input, param) {

        //this.input = input;
        var pointer = this;
        this.input = input;
        this.param = param;
        // clear if we get unfocused
        $(input).blur(function() {
           // pointer.clear();    
        });

		$(input).keyup(function(event)
		{
			var search;
            var RETURN = 13;
            var TAB = 9;
            var ESC = 27;
            var UP = 38;
            var DOWN = 40;



            switch(event.keyCode) {
                case RETURN:
                    pointer.sendSelection();
                    break;

                case ESC:
                    pointer.clear();
                    break;

                 case UP:
                    pointer.setSelection("up");
                    break;


                 case DOWN:
                    pointer.setSelection("down");
                    break;

                default:
        			pointer.search($(input).val());
                    break;
           }

		});

}

IDA.gui.quickSearch.prototype.search = function (search) {

    var cl = "";
    var pointer = this;
    if(typeof(this.param.className) != "undefined")
        cl = " class='" + this.param.className + "'";

    if (search.length > 0)
    {
        $.ajax(
        {
            type: "POST",
            url: "../idacore/server/xmlin.php",
            data: "xml=" + "<quicksearch "+ cl + ">" + search + "</quicksearch>",
            dataType: "xml",
            success: function(message) {	

               if(!pointer.div) {
                   pointer.div = $("<div class='ida-autocomplete'>")[0];
                    $("body").append(pointer.div);

                    var offset = $(pointer.input).offset();

                    $(pointer.div).css("left", offset.left + "px");
                    $(pointer.div).css("top", offset.top  + pointer.input.offsetHeight + "px");
                    $(pointer.div).css("width", pointer.input.offsetWidth + "px");
                }

                $(pointer.div).empty();
                $(pointer.div).append("<ul>");
                $(message).find("rs").each(function() {
                    $(pointer.div.firstChild).append("<li rec_id='"+ $(this).attr("id")  +"'>"+this.textContent+"<div class='info'>"+  $(this).attr("class") +"</div></li>");
                });
                $("li", pointer.div).click(function () {
                    pointer.sendSelection_c(this);
                });
            }
        });
    }
    else {
        $(".ida-autocomplete").empty();
    }
}

IDA.gui.quickSearch.prototype.setSelection = function (dir) {

    var cur = $(".hilite", this.div);

    if(cur.length) {
        $(cur).removeClass("hilite");
        if(dir == "down")
            $(cur).next().addClass("hilite");
        else
            $(cur).prev().addClass("hilite");
            
    } else {
        $("li:first", this.div).addClass("hilite"); 
    }

}

IDA.gui.quickSearch.prototype.sendSelection = function () {
    var sel = $(".hilite", this.div);
    if(sel.length) {
        this.clear();
        this.param.callback({id:sel.attr("rec_id"), value:sel.text(), className:this.param.className}, this.input);
    }
}

IDA.gui.quickSearch.prototype.sendSelection_c = function (sel) {
    this.clear();
    this.param.callback({id:$(sel).attr("rec_id"),value:$(sel).text(), className:this.param.className}, this.input);
}


IDA.gui.quickSearch.prototype.clear = function () {

    $(this.div).remove();
    this.div = null;
    $(this.input).val(""); 

}

function findPos(ele) {

    var curleft = 0;
    var curtop = 0;
    if(ele.offsetParent) {
        while (ele.offsetParent) { 
            curleft += ele.offsetLeft;
            curtop += ele.offsetTop;
            ele = ele.offsetParent;
        }

    }

    return {x:curleft, y:curtop};
}




/**
    * @private
*/

// open template for files and insert file link to the template
IDA.gui.openFileData = function (ele) {

    var div = d.getElementById("idauploadsholder");
    IDA.gui.cleanDiv(div);
    
    var fileId = ele.getAttribute("rid");
    var fileName = ele.getAttribute("fname");
    var ftype = ele.firstChild.nodeValue;       // not translateable when done like this!!
    
    var img = IDA.gui.createElement("img", {src:"data/thumbnails/" + fileName + ".jpg"});
    div.appendChild(img);

    var inputDiv = d.createElement("div",{id:"idadatainputholder"});
    div.appendChild(inputDiv);


    var xml   = "   <data>\n";
    xml = xml + "       <P128B.is_carried_by>\n";
    xml = xml + "           <filename id=\"" +  fileId + "\" />\n";
    xml = xml + "       </P128B.is_carried_by>\n";
    xml = xml + "   </data>\n";

    IDA.gui.openTemplate(ftype, inputDiv);

}


/**
    * @private
*/
IDA.gui.startLogin = function () {

    // load SHA1.js dynamically
    if(typeof SHA1 == "undefined") {

        $.getScript("../idacore/client/webtoolkit.sha1.js", function() {
            IDA.gui.submitLogin();    
        });

    } else {

        IDA.gui.submitLogin();
    }

}

IDA.gui.submitLogin = function () {


    var user = d.getElementById("idauser");
    var pass = d.getElementById("idapass");
    var hashPass = SHA1(pass.value);
    var hashed = SHA1(hashPass + IDA.loginSeed);
    IDA.user = user.value;

    if(user.value.length && pass.value.length) {

    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<login>\n";
    xml = xml + "   <user>\n";
    xml = xml + "       " + user.value + "\n";
    xml = xml + "   </user>\n";
    xml = xml + "   <pass>\n";
    xml = xml + "       " + hashed + "\n";
    xml = xml + "   </pass>\n";
    xml = xml + "   <seed>\n";
    xml = xml + "       " + IDA.loginSeed + "\n";
    xml = xml + "   </seed>\n";
    xml = xml + "</login>\n";

    var p = function (xml) { IDA.gui.loginCallback(xml); };
    var e = function (error) { alert(error); };
    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p,e);

    } else {

    alert("You need to give both username and password!");

    }


}

IDA.gui.toggleList = function (disp) {
    var listDiv = IDA.gui.checkDiv("IdaClassList");
    if(listDiv)
        listDiv.style.display = disp;

}
/**
    * @private
*/

IDA.gui.createTextInput = function (info) {

    // render long fields as textareas
    if(info["size"] > 120) {
        var input = d.createElement("textarea");
       // input.style.width = "10em";

    } else {
        var input = d.createElement("input");
        input.style.width = info['size'] + 'em';
    }
    
    input.style.className = "textinput";
    input.name = info['c_name'];

    // let's add also id  for test functions
    input.id = info['c_name'];

    if(info['passwd'] == "yes")
        input.type = "password";

    return input;
}

IDA.gui.createFieldWithTitle = function (info) {

    return IDA.gui.createElement("div",{}, info["value"]);

}



/**
    * @private
*/
IDA.gui.createLabeledTextInput = function (xml, div) {

    var style = "";
    var val = "";
    var l = $(xml).attr("width");
    var tr = xml.tagName;
    var disp = $(xml).attr("display");
    if(disp == "float")
        style = "float:left";

    if(disp == "none")
        style = "display:none"; 

    if(xml.hasChildNodes()){
        val = xml.firstChild.textContent;
    }

    if(xml.tagName == "start_extension")
        IDA.gui.createTimeExtensionMenu(tr, xml.tagName,div);



    if(disp != "none")
        if(l < 120)
            $(div).append("<div style='"+style+"'><div>" + tr + "</div><input style=\"width:" + l + "em\"  value =\""+ val +"\"  name=\""+ xml.tagName +"\"></div>");
        else
            $(div).append("<div><div>" + tr + "</div><textarea  name=\""+ xml.tagName +"\">"+ val +"</textarea></div>");
            
}


IDA.gui.createTimeExtensionMenu= function (tr, name, div) {

    $(div).append("<div style='float'><div>" + tr + "</div><select name='"+name+"'><option value='0'>extension</option><option value='1'>luku</option></select></div>");
    $("select").change(function() {
        var root = this.parentNode.parentNode;
        var year =$("[name=start_year]",root).val();
        var c = year.charAt(year.length - 1);
        if (c != '0')
            alert("Cannot set!");
    });

}

/**
    * @private
*/

IDA.gui.createFakeLink= function (linkName, func, id) {

    var textLink = IDA.gui.createElement("div",{className:"fakelink"}, linkName);


    // if we have record id, we write it to the dom node
    if(id) {
        textLink.setAttribute("rec_id", id);
    }
    
    if(func) {
        textLink.onclick = function () {func(this)};
    } 

    return textLink;

}


/**
    * @private
*/

IDA.gui.createButton = function (title) {
    return $("<div class='button'>").append(title)[0];
}

/**
    * @private
*/
IDA.gui.clearInputs = function (input_div) {
    var inputs = input_div.getElementsByTagName("input");
    for(var i = 0; i < inputs.length; i++) {
    inputs[i].value = "";
    }
}

/**
    * function modified from Timothy Groves's Autosuggest
    * @private
*/

IDA.gui.createElement = function (type, attributes, elText) {

    var ne = document.createElement( type );
    if (!ne)
    return false;

    for (var a in attributes)

    ne[a] = attributes[a];

    if (typeof(elText) == "string")
    ne.appendChild( document.createTextNode(elText) );

    return ne;
}

/**
    * @private
*/
IDA.gui.cleanDiv = function   (ele) {

   
    while (ele.childNodes[0])
    {
    ele.removeChild(ele.childNodes[0]);
    }
    

}

/**
    * removes numeric property id from title (P70F etc.)
*/
IDA.gui.getPropertyTitle = function (name) {
   
   return name;
    var n = name.replace("_",".");
    var splitted = n.split('.');
    if(splitted[1]) {
        var str = splitted[1];
        return str.replace(/_/g, " ");
    }

}

IDA.gui.reverseLink = function (link) {

    var splitted = link.split(".");
    var linkId = splitted[0];
    var dir = linkId.charAt(linkId.length-1);
    if(dir == "F")
        return linkId.replace(/F/g, "B") + ".reversed";
    else
        return linkId.replace(/B/g, "F") + ".reversed";


}




/**
    * formulate date
    * @private
*/
IDA.gui.formDate = function (date) {
    var start = new Array();
    var end = new Array();

    for(var j = 0; j < date.childNodes.length; j++) {

        var tag = date.childNodes[j].tagName;

        // if there is a value then proceed
        if(date.childNodes[j].childNodes.length) {

            if (tag.indexOf("start") == 0) {
                //alert(date.childNodes[j].firstChild.nodeValue);
                start.push(date.childNodes[j].firstChild.nodeValue);
            } else {
                end.push(date.childNodes[j].firstChild.nodeValue);
            }
        }
    }


    if(!end.length)
        return start.join('.');

    return start.join('.') + " - " + end.join('.');
}


/**
    * @private
*/
IDA.gui._XMLAttributesToArray = function (xml) {

    info = new Array();

    if(xml.tagName) {

        info['title'] = xml.tagName;
        info['filename'] = xml.getAttribute("filename");
        info['type'] = xml.getAttribute("type");
        info['size'] = xml.getAttribute("width");
        info['input_id'] = xml.getAttribute("id");
        info['display'] = xml.getAttribute("display");
        info['prefix'] = xml.getAttribute("prefix");
        info['required'] = xml.getAttribute("required");
        info['hidden'] = xml.getAttribute("hidden");
        info['row_id'] = xml.getAttribute("id");
        info['c_name'] = xml.tagName;
        info['value'] = "";
        if (xml.firstChild) {
            info['value'] = xml.firstChild.nodeValue;
    }

    return info;
    }

}




IDA.record = function (browser) {

    this.browser = browser;
    this.renderFlag = true;

}

// get xml for first saving of the record
IDA.record.prototype.getXML = function (d) {

    var xml = "";
    var pointer = this;
    xml = this.getDataXML(d, "  ");

     // let's browse throug all properties in DOM and create XML 
    $(d).children("div[id='properties']").children("div").children("[link]").each(function() {
        xml = xml + pointer.getLinksXML(this, "");

        // browse through "create_by_default" links
        $(this).find("[rec_class]").not("[rec_id]").each(function() {
            
            var link = $(this.parentNode).attr("link");
            xml = xml + "<" + link + ">\n";
            xml = xml + "  <" + $(this).attr("rec_class") + ">\n";
            xml = xml + pointer.getDataXML(this, "    ");

            $(this).children("[link]").each(function() {
                xml = xml + pointer.getLinksXML(this, "    ");
            });

            xml = xml + "  </" + $(this).attr("rec_class") + ">\n";
            xml = xml + "</" + link + ">\n";
        });


    });

       
     
    return xml;
}

// read links when editing
IDA.record.prototype.getLinksEditXML = function (d) {

    var xml = "";
    var subjectId = d.getAttribute("rec_id");
    // browse through links
    $(d).children("[rec_id]").each(function() {

        var com = 0;
        var link = $(this.parentNode).attr("link");

        if($(this).is(":hidden"))
            var com = "unlink";
         else if(this.hasAttribute("new"))
            var com = "link";

        if(com)
            xml = xml + "<" + com + " subject_id=\"" + subjectId + "\" link=\""+ link +"\" target_id=\""+ $(this).attr("rec_id") +"\"/>\n";
    });

    return xml;
}

// read links when adding a new record
IDA.record.prototype.getLinksXML = function (d, sep) {

    var xml = "";
    // browse through links
    $(d).children("[rec_id]").each(function() {
        var link = $(this.parentNode).attr("link");
        var recClass = $(this).attr("rec_class");
        if(!recClass)
            recClass = "record";

        xml = xml + sep + "<" + link + ">\n";
        xml = xml + sep + "    <" + recClass + " id=\"" + $(this).attr("rec_id") + "\" />\n";
        xml = xml + sep + "</" + link + ">\n";
    });

    return xml;
}


// read data fields per property 
IDA.record.prototype.getDataXML = function (d, sep) {

    var pointer = this;
    var xml = "";

     // data fields are inside divs with attribute "link"
    $(d).children("[id='data']").children("div[link]").each(function() {

        var property = this.getAttribute("link");
        xml = xml + sep + "<" + property + ">\n";
        xml = xml + IDA.gui.getDataFieldsXML(this,sep);
        xml = xml + sep + "</" + property + ">\n";
    });

   return xml;

};


IDA.gui.getDataFieldsXML = function (d, sep) {

    var xml = "";

    // data is in inputs or textareas
    $(d).children().find("input,textarea,select").each(function() {
        if(this.value != "") {
            xml = xml + sep + "  <" + this.name + ">" + this.value + "</" + this.name + ">\n";
            
        }  
    });

    return xml;

}



// compare properties from template to properties from record
IDA.record.prototype.applyTemplate = function (templateXML,recordXML) {

    var pointer = this;

    $(templateXML).children().not("[table]").each(function() {
        var property = this;
        var found = 0;

        $(recordXML).children(property.tagName).each(function() {

            if(property.hasAttribute("action")) {

                this.setAttribute("action",property.getAttribute("action"));
                this.setAttribute("group",property.getAttribute("group"));

                if(property.getAttribute("action") != "create_by_default") {
                    this.setAttribute("class",property.getAttribute("class"));

                } else {

                    pointer.applyTemplate(property.firstChild,this.firstChild);
                }

            found = 1;

            }
        });

        if(!found) {
            $(recordXML).append(property);
        }

    });

    this.applyTemplateTables(templateXML,recordXML);

}


IDA.record.prototype.applyTemplateTables = function (templateXML,recordXML) {

    // copy missing tables
    $(templateXML).children("[table]").each(function() {
        var tem = this;
        // if not found then add
        s = $(recordXML).children(this.tagName);

        // if there is a match, then add attribute "table"
        if(s.size()) {

            $(recordXML).children(this.tagName).attr("table", $(this).attr("table"));

        } else {

            $(recordXML).append(this);
        } 
            
    });

}



// set ajax selection from external source
IDA.record.prototype.setSelection = function (xml, fValue, domNode) {
    
    var hit = xml.responseXML.getElementsByTagName("rs");
    if(hit.length == 0)
        alert("\"" + fValue + "\" not found!");
        
    if(hit.length == 1) {
        this._add2Selected(hit[0], domNode);
    }

    if(hit.length > 1) {
        alert("Multiple hits found!");
    }
    
}



/**
    * find time attributes, returns string
*/
IDA.record.prototype.getTimeAttributes = function (xml) {


    if(xml.hasAttribute("P4F.has_time-span"))
        return xml.getAttribute("P4F.has_time-span");

}


/**
    * find identifier attributes, returns string
*/
IDA.record.prototype.getNameAttributes = function (xml) {
    var pointer = this;
    var func = function(node) {pointer.browser.load(node.getAttribute("rec_id"))};
    var name = "";

    if(xml.hasAttribute("P1F.is_identified_by"))
        name = xml.getAttribute("P1F.is_identified_by");

    else if(xml.hasAttribute("P131F.is_identified_by"))
        name = xml.getAttribute("P131F.is_identified_by");

    else if(xml.hasAttribute("P87F.is_identified_by"))
        name = xml.getAttribute("P87F.is_identified_by");

    else if(xml.hasAttribute("P2F.has_type"))
        name = xml.getAttribute("P2F.has_type");

    // OK, if nothing found then try design and creator
    $("Production",xml).each(function() {
        if(this.hasAttribute("P33F.used_specific_technique"))     
            name = name + " " + $(this).attr("P33F.used_specific_technique");     
    });

    $(xml).parent().parent().each(function() {
        if(this.hasAttribute("P33F.used_specific_technique"))     
            name = name + " " + $(this).attr("P33F.used_specific_technique");     
    });

    if(name == "")
        name = xml.tagName + " (id:" + xml.getAttribute("id")+ ")";

    if(this.browser.editMode)
        return $("<div class='selInstance' rec_id='" + $(xml).attr("id") + "'>"+ name + "</div>");
    else
        return IDA.gui.createFakeLink(name, func, $(xml).attr("id"));




}





IDA.record.prototype._addAjaxHandler = function (className, ele) {

    var input = $(ele).find("input")[0];

    var pointer = this;
    var options = {
        callback:function (ob, dom) {pointer._add2Selected(ob, dom) },
        className:className
    };

    var ajaxHandler = new IDA.gui.quickSearch(input, options);
    

}

IDA.record.prototype._removeSelection = function (domNode) {
    

    var recId = domNode.parentNode.getAttribute("rec_id");
    var parentId = domNode.parentNode.parentNode.getAttribute("parent_id");
    var linkId = domNode.parentNode.parentNode.getAttribute("link_id");
    var mode = "unlink";

    var xml = "<?xml version='1.0'?>\n";
    xml = xml + "  <" + mode + " record=\"" + parentId + "\">\n";
    xml = xml + "    <" + linkId + ">\n";
    xml = xml + "      <class id=\"" + recId + "\"/>\n";
    xml = xml + "    </" + linkId + ">\n";
    xml = xml + "  </" + mode + ">\n";
    
    if (DEBUG) alert (xml);

    var p = function () { IDA.gui._removeParentNode(domNode); };
    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + "xmlin.php", xml, p);
        
     
    
}




IDA.record.prototype._removeSearchAddEdit = function (div) {

    var pointer = this;
    this.browser.open_edit = false;
    $(div).removeClass("selInstance");

    $(div).children(".button").show();
    $(div).children(".ida_search").remove();


    // make things to back to normal
    $(div).children("[rec_id]").each(function() {
        if(this.hasAttribute("new")) {
            $(this).remove();
        } else {
            $(this.lastChild).remove();
            $(this).show();
            if(!pointer.browser.editMode){
                $(this).removeClass("selInstance");
                this.onclick = function() {pointer.browser.load(this.getAttribute("rec_id"))};
                $(this).addClass("fakelink");
            }
        }
    });

    $(div).children(".buttonHolder").remove();
}


// render record search when editing record
IDA.record.prototype._renderSearchAddEdit = function (className, div) {
    
    if(this.browser.open_edit){
        alert("Save your previous edit first!");
        return;
    }

    var pointer= this;
    this.browser.open_edit = true;

    $(div).addClass("selInstance");
    $(div).children(".button").hide();

    $(div).children("[rec_id]").each(function() {
        pointer._add2Selected(this, div);
    });

    this._renderSearchAdd(className, div);
    var save = IDA.gui.createButton("save");
    var cancel = IDA.gui.createButton("cancel");
    $(div).append("<div class='buttonHolder'><div>-----------</div></div>");
    $(div.lastChild).append(save);
    $(div.lastChild).append(cancel);
    save.onclick = function () {pointer._sendLinkEdits(this.parentNode.parentNode,"");};
    cancel.onclick = function () {pointer._removeSearchAddEdit(this.parentNode.parentNode)};

}




// render record with search field and "add new" -link
IDA.record.prototype._renderSearchAdd = function (className, div) {

    var pointer = this;

    // create search field
    $(div).append("<div class='ida_search'><div>search " + className + ":</div><input></div>");

    // set ajax search
    pointer._addAjaxHandler(className, div.lastChild);   
    
    bHolder = IDA.gui.createElement(  "div", {className:"buttonHolder"});
    
    // "add new" link
    add = IDA.gui.createElement(  "div", {className:"button"},"add new ");
    add.onclick = function () { 
        $(this.parentNode.lastChild).append("<div/>");
        IDA.tree.classTree(this.parentNode.lastChild.lastChild, null, className, "all");
        $(this.parentNode.lastChild.lastChild).dialog({title: 'Class Picker'});
        return false; 
        };
    
    bHolder.appendChild(add);  
   
    // "show all" link
    show = IDA.gui.createElement(  "div", {className:"button"},"show all " );
    show.onclick = function (e) { IDA.gui.listAll(className, this.parentNode.lastChild);return false };
    bHolder.appendChild(show);
    
    bHolder.appendChild(IDA.gui.createElement("div")); // for class list
    $(div).append(bHolder);
    
}



IDA.record.prototype._makeEditXML = function (mode, record, splitted) {

    var xml = "<?xml version='1.0'?>\n";
    xml = xml + "  <" + mode + " record=\"" + record.parentId + "\">\n";
    xml = xml + "    <" + record.linkName + ">\n";
    xml = xml + "      <" + splitted[0] + " id=\"" + splitted[1] + "\"/>\n";
    xml = xml + "    </" + record.linkName + ">\n";
    xml = xml + "  </" + mode + ">\n";
     
    return xml;
  

}





IDA.record.prototype.renderGroup = function(groupName, xml, d) {

    $(xml).children("[group=" + groupName + "]").each(function() {

        alert(this.tagName);
    });

}

IDA.record.prototype.renderDataForms = function(xml, d) {

    $(xml).children("[table]").each(function() {
         
        if(this.hasAttribute("translation"))
            tr = this.getAttribute("translation")
        else
            tr = this.tagName;

        $(d).append("<div link=\""+ this.tagName +"\"><p>" + tr + ":</p></div>");

        // render fields
        $(this).children().each(function() {
            IDA.gui.createLabeledTextInput(this, d.lastChild);
        });

        $(d).append("<div class='clear'>");

    });

}

IDA.record.prototype._sendLinkEdits = function (d) {
   
   var pointer = this;
    var recId = d.getAttribute("rec_id");
        
     xml = pointer.getLinksXML(d,"");
    var xml = "<?xml version='1.0'?>\n";
    xml = xml + "<editlinks>\n";
    xml = xml + pointer.getLinksEditXML(d);
    xml = xml + "</editlinks>\n";

    if (DEBUG) alert(xml);
 
    var p = function () { pointer.browser.load(pointer.browser.id); };

    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + "xmlin.php", xml, p);



}
/*
 IDA.gui._sendData = function (d) {
   var root = d.parentNode.parentNode;
   var rowId = root.getAttribute("row_id");
   var recId = root.getAttribute("rec_id");
   var link = root.getAttribute("link");

    // are we adding or editing row?
    if(rowId)
        mode = "editdata";
    else
        mode = "adddata";
        
    var xml = "<?xml version='1.0'?>\n";
    xml = xml + "<" + mode + " record=\"" + recId + "\" row=\""+rowId+ "\" link=\""+link+"\">\n";

    xml =  xml + IDA.gui.getDataFieldsXML(d.parentNode.parentNode,"  "); 

    xml = xml + "</" + mode + ">\n";
    
   alert(xml);
    if (DEBUG) alert(xml);
  */   
/*
    var p = function () { pointer.editCallback(); };

    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + "xmlin.php", xml, p);
*/
//}



IDA.record.prototype.renderDataFields = function(xml, d) {

        // render fields
        $(xml).children().each(function() {
            IDA.gui.createLabeledTextInput(this, d);
        });
 
        $(d).append("<div class='clear'>");

}


IDA.record.prototype._add2Selected = function (content, dom) {

    var func = null;
    var pointer = this;


    if(content.nodeType == 1) {
        // initialising edit
        if(content.hasAttribute("rec_id")) {
            content.onclick = null;
            $(content).removeClass("fakelink");
            $(content).addClass("selInstance");
            $(content).append("<a class='delete' title='remove'>X</a>");
            content.lastChild.onclick = function () { $(this.parentNode).hide();  };
        } else {
            // setSelection() gives us a xml node
            var sel = IDA.gui._makeAjaxSelection(content.firstChild.nodeValue, func);
            var splitted = content.getAttribute("id").split(":");
            sel.setAttribute("rec_id", splitted[1]);
            sel.setAttribute("rec_class", splitted[0]);
            dom.insertBefore(sel, dom.firstChild);
        }
       
    // otherwise content is object with .value and .id
    } else {
        selection = content;
  
        var sel = IDA.gui._makeAjaxSelection(selection.value, func);

        // write record id to selection
        // - id coming from quicksearch-gui = class:id
        // - edit rendering uses only id
//        if(splitted.length == 1) {
            sel.setAttribute("rec_id", selection.id);
            sel.setAttribute("rec_class", selection.className);
  /*      } else {
            sel.setAttribute("rec_id", splitted[1]);
            sel.setAttribute("rec_class", splitted[0]);
        }
*/
        // let's add a flag so that we know which selections are new (editing)
        sel.setAttribute("new","true");

        var found = 0;
        // check that this is not allready selected
        $(dom.parentNode.parentNode).children("[rec_id]").each(function() {
            if($(this).attr("rec_id") == $(sel).attr("rec_id")) {
                if($(this).is(":hidden"))
                    $(this).show();
                found = 1;
            }
        });

    
        if(!found)
            dom.parentNode.parentNode.insertBefore(sel, dom.parentNode.parentNode.firstChild);   
    } 
 }



/**
    * @private
*/
IDA.Ajax = function () {
    if (window.XMLHttpRequest) {
        this.xmlHttp = new XMLHttpRequest();
    }
    else if (window.ActiveXObject) {
        this.xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
}

/**
    * @private
*/
IDA.Ajax.prototype.sendXML = function (addr, xml, onComp, onErr) {
    var pointer = this;
    this.onComplete = onComp;
    this.onError = onErr;
    var query = encodeURI(xml);

    this.xmlHttp.onreadystatechange = function () {pointer.handleStateChange();}
    this.xmlHttp.open("POST", addr, true);
    this.xmlHttp.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
    this.xmlHttp.send("xml="+query);
}

/**
    * @private
*/
IDA.Ajax.prototype.sendPostRequest = function (addr, q, onComp, onErr) {
    var pointer = this;
    this.onComplete = onComp;
    this.onError = onErr;

    this.xmlHttp.onreadystatechange = function () { pointer.handleStateChange() };
    this.xmlHttp.open("POST", addr, true);
    this.xmlHttp.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
    this.xmlHttp.setRequestHeader("Accept-Charset","UTF-8");
    this.xmlHttp.send(q);

}

/**
    * @private
*/
IDA.Ajax.prototype.sendGetRequest = function (q, onComp, onErr) {
    var pointer = this;
    this.onComplete = onComp;
    this.onError = onErr;

    this.xmlHttp.onreadystatechange = function () { pointer.handleStateChange() };
    this.xmlHttp.open("GET", q, true);
    this.xmlHttp.send(null);

}

/**
    * @private
*/
IDA.Ajax.prototype.handleStateChange = function () {

    //var statusArea = document.getElementById("statusArea");
    if (this.xmlHttp.readyState == 4) {
        if (this.xmlHttp.status == 200) {
            
            
            // errors in server end
            var e = this.xmlHttp.responseXML.getElementsByTagName("error");

            if(e.length) {
                if(this.onError)
                    this.onError(e[0].firstChild.nodeValue);
                else
                    alert("ERROR: " + e[0].firstChild.nodeValue);
            } else {
                this.onComplete( this.xmlHttp );
            }
            
        // connect/server errors    
        } else {
            if (xmlhttp.status==404) alert("URL doesn't exist!")
            else this.onError( this.xmlHttp.status );
        }
    }
}


    /**
    * @namespace IDA.tree
    */
    IDA.tree = {};

    /**
        * Fetch and display class tree
        * @param {String}class record class for example "Person" 
        * @param {String|DOMnode}divName id of div element or DOM-node where to put display
    */
    IDA.tree.classTree = function (divName, func, className, settings, url) {

        var classTitle = "";
        // check if this is dom node or node name
        var domNode = IDA.gui.getDiv(divName);
        IDA.gui.cleanDiv(domNode);

        if(className)
            classTitle = " title=\"" + className + "\"";
 
        // set default functions if user has not defined one
        if (!func) {
            func = function (ele) { IDA.gui.openTemplate(ele.getAttribute("title")); }
        }


        var p = function (req) { IDA.tree.classTreeCallBack(req, domNode, func, settings) };
        var e = function (status) { domNode.appendChild(IDA.gui.createElement("p",{className:"error"}, className + " not found!")); };


        var ad = IDA.serverPath + "xmlin.php";

        xml = "<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<classtree " + classTitle + "/>\n";

        var ajax = new IDA.Ajax;
        if(url)
            ajax.sendXML(url,xml, p,e);
        else
            ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p,e);

        }



    IDA.tree.classTreeCallBack = function (xml, domNode, func, settings) {

        IDA.tree.round = 0;
        var root = xml.responseXML.getElementsByTagName("root")[0];
        var treeNode = $("<div><ul/></div>");
        IDA.tree.renderTreeFromXML(root, $("ul",treeNode)[0], func);
        $(domNode).append(treeNode);
        $(treeNode).treeview(settings);
    }




    IDA.tree.renderTreeFromXML = function (xml, treeNode, func, settings) {

        if(IDA.tree.round == 0) {
            var ul = treeNode;
            IDA.tree.round =+ 1;
        } else if (xml.hasChildNodes()) {
            var ul = $("<ul>");
            $(treeNode).append(ul);
        }

        
        for(var i=0; i < xml.childNodes.length; i++) {
            
            IDA.tree.makeTreeLeaf(xml.childNodes[i], ul, func);
 
        }

    }

    IDA.tree.makeTreeLeaf = function (xml, treeNode, func) {

        var classId = "";
        var classType = xml.tagName;
        var id =  xml.getAttribute("id");

        // let's not display dummy root node from place hierarchy 
        if(id == 0) {
            IDA.tree.renderTreeFromXML(xml, treeNode, func);
            return;
        }

        if(xml.hasAttribute("name")) 
            var titleText = xml.getAttribute("name");
        else
            titleText = xml.getAttribute("title");

        var title = $("<a href='#'>" + titleText + "</a>");

        title.attr("rec_id", $(xml).attr("id"));

        if(func)
            title.click(function() { func(this); });

        var li = $('<li class="onto_' + xml.tagName + '"></li>');
        $(title).attr('title', titleText);
        $(li).append(title);
        $(treeNode).append(li);
        
       // dive to recursion, 1
        IDA.tree.renderTreeFromXML(xml, li[0], func);

    }


// from http://www.finefrog.com/2008/05/29/php-in_array-equivalent-for-javascript/
function in_array(needle, strict) {
    for(var i = 0; i < this.length; i++) {
        if(strict) {
            if(this[i] === needle) {
                return true;
            }
        } else {
            if(this[i] == needle) {
                return true;
            }
        }
    }

    return false;
}



Array.prototype.in_array = in_array;

