/**
 * @fileoverview Simple example of browser script
 *
 * @author Ari H&auml;yrinen
 */


if (typeof(IDA) == "undefined") {
    alert("IdaBase is not loaded!");
}


/**
    * IDA.browser
    * @namespace IDA.browser
*/
IDA.browser = function (divName) {
    if(typeof(divName) != "undefined") {
    // check if this is dom node or node name
        this.div = IDA.gui.checkDiv(divName);
    }

    if(typeof(this.div) == "undefined") {
        $("body").append("<div>");
        this.div = $("body").children(":last")[0];
        this.popup = true;
    }

    this.editMode = false;


    
}


IDA.browser.prototype.loadTemplate = function (class, callBack) {

    this.callBack = callBack;
    var day= new Date(); 
    this.rand = day.getTime(); 
    if(this.popup)
        $(this.div).dialog( { modal:true, title: "edit window", width: 450, position:['center','top'] , close:function(ev,ui) {$(this).remove();}});

    var pointer = this;
    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<gettemplate title=\"" + class + "\">\n";
    xml = xml + "</gettemplate>\n";

    // set callback for ajax
    var p = function (req) { pointer.renderTemplate(req) };
   // var e = function (status) { alert("AJAX error: "+status); };

    this.ajax = new IDA.Ajax;
    this.ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p);

}

/**
    * Load record
    * @param {Integer}id record id
    * @param {String|DOMnode}divName id name or div element for display
*/



IDA.browser.prototype.load = function (id) {

    var pointer = this;
    // flag for open edits
    this.open_edit = false;
   
    if(typeof(id) == "undefined") 
        return;
   
    if(typeof(id) == "string" || typeof(id) == "number")
        this.id = parseInt(id);
    else if(id.id != "undefined")
        this.id = id.id;
    else
        alert("Missing id!");


    var p = function (req) { pointer.render(req) };

    // TODO
    if(this.editMode == true)
        var action = "editable";
    else
        var action = "";

    // get the content from server
    xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<get mode=\"" + action + "\"><record id=\"" + this.id + "\" /></get>";


    this.ajax = new IDA.Ajax;
    this.ajax.sendXML(IDA.serverPath + 'xmlin.php',xml, p);
}



IDA.browser.prototype.renderTemplate = function (xml) {

    var pointer = this;
    var root = xml.responseXML;
   // IDA.gui.cleanDiv(this.div);     // remove all other content

     // create xml-link so that user can view the xml
    var showXML = $("<a href='#' title='show XML'>XML</a>");
    showXML.click(function() {
        $("body").append("<div id='xmldiv'>");
        IDA.gui.showXML($("#xmldiv")[0], xml.responseXML.firstChild);
        $("#xmldiv").dialog({modal:true, title: 'login', width: 450, position:['center','center'], close:function(ev,ui) {$(this).remove()}});
        return false;
    });
    $(this.div).append(showXML);

   
    // parse xml as a template
    this.initCurrentRecord(xml);
    this.record.browser = this;

    this.div.appendChild(IDA.gui.createElement("h1",{className:"ida_Header"}, this.record.className));
    
    // render info for class
    if(typeof(this.record.info) != "undefined")
        this.div.appendChild(IDA.gui.createElement("p",{className:"box"}, this.record.info));

    
    // render data
    $(this.div).append('<div id="data"></div>');
    if(this.editMode)
       this.renderDataEditable(root.firstChild.firstChild, this.div.lastChild); 
    else
        this.record.renderDataForms(root.firstChild.firstChild, this.div.lastChild);

    // render groups
    var groups = Array();
    $(root.firstChild.firstChild).children("[group]").each(function() {
        if(!groups.in_array(this.getAttribute("group")))
            groups.push(this.getAttribute("group"));
    });

    // add group holder
    $(this.div).append('<div id="properties"><ul id="ida_temp"></ul></div>');

    // render
    for(var i=0; i < groups.length; i++) {
        if(this.editMode)
            this.renderGroupEdit(groups[i], root.firstChild.firstChild);
        else
            this.renderGroup(groups[i], root.firstChild.firstChild);
    }

    // make groups tabbed
    $(this.div.lastChild).tabs();

    // add buttons
    if(!this.editMode)
        IDA.gui.addSaveCancelBtns(this);

    if(this.callBack)
        this.callBack(pointer.div);
}


IDA.browser.prototype.renderGroup = function (groupName, xml) {

    var pointer = this;

    $(this.div).find("#ida_temp").append('<li><a href="#t_'+groupName+'">'+groupName+'</a></li>');
    var tab = $('<div id="t_'+groupName+'"></div>')[0];
    $('#properties', this.div).append(tab);

    $(xml).children("[group="+groupName+"]").each(function() {

        var root = this.firstChild;
        var act = $(this).attr("action");

        if($(this).attr("translation"))
            $(tab).append("<div class='property_title'>"+$(this).attr("translation")+"</div>");
        else
            $(tab).append("<div class='property_title'>"+this.tagName+"</div>");

        $(tab).append("<div link='" + this.tagName + "'></div>");

        if(act == "create_by_default") {

            // property target
            $(this).children().each(function() {

                $(tab.lastChild).append("<div rec_class='" + this.tagName + "'><h3>"+this.tagName+"</h3><div id='data'></div></div>");
                pointer.record.renderDataForms(this, $(tab).find("#data")[0]);

                // get target's own properties
                $(root).children().not("[table]").each(function() {

                    var linkName = IDA.gui.getPropertyTitle(this.tagName);
                    $(tab.lastChild.lastChild).append("<div class='property_title'>"+linkName+"</div>");
                    $(tab.lastChild.lastChild).append("<div link='" + this.tagName + "'></div>");
                    pointer.record._renderSearchAdd(this.getAttribute("class"), tab.lastChild.lastChild.lastChild);

                });
            });

        // LINKS
        } else {

            pointer.record._renderSearchAdd($(this).attr("class"), tab.lastChild);
          
        }
    }); 
}



IDA.browser.prototype.renderGroupEdit = function (groupName, xml) {

    var pointer = this;
    $(this.div).find("#ida_temp").append('<li><a href="#t_'+groupName+'">'+groupName+'</a></li>');
    var tab = $('<div id="t_'+groupName+'"></div>')[0];
    $('#properties', this.div).append(tab);

    $(xml).children("[group="+groupName+"]").each(function() {

        var root = this.firstChild;
        var act = $(this).attr("action");

        if($(this).attr("translation"))
            $(tab).append("<div class='property_title'>"+$(this).attr("translation")+"</div>");
        else
            $(tab).append("<div class='property_title'>"+this.tagName + " " + $(this).attr("class") + "</div>");

        $(tab).append("<div link='" + this.tagName + "' rec_id='" + pointer.id + "'></div>");

        if(act == "create_by_default") {

            // property target
            $(this).children().each(function() {
                
                var rec_id = $(this).attr("id");
                $(tab.lastChild).append("<div rec_class='" + this.tagName + "'><h3>"+this.tagName+"</h3><div id='data'></div></div>");
               // render data fields
               pointer.renderDataEditable(this, $(tab).find("#data")[0]); 

                // get target's own properties
                $(root).children().not("[action='input']").each(function() {

                    var linkName = this.tagName;
                    var tClass = $(this).attr("class");
                    $(tab.lastChild.lastChild).append("<div class='property_title'>"+linkName + " " + tClass + "</div>");
                    $(tab.lastChild.lastChild).append("<div link='" + this.tagName + "' rec_id='" + rec_id + "'></div>");
                    $(tab.lastChild.lastChild.lastChild).append("<div class=\"button\">edit</div>");
                tab.lastChild.lastChild.lastChild.lastChild.onclick = function () {pointer.record._renderSearchAddEdit(tClass, this.parentNode);}

                // list selected values   
                $(this).children().each(function() {
                    $(tab.lastChild.lastChild.lastChild).append("<div class='selInstance' rec_id='" + $(this).attr("id") + "'>"+ $(this).find("name").text() + "</div>");
                });


                });
            });

        // LINKS
        } else {

            
            IMG2_PATH = "../data/thumbs/";
            var tClass = $(this).attr("class");

            // treat images differently
            if(tClass == "Digital_Image") {
 
                $(tab.lastChild).append("<div id=\"kuvat\"><div id=\"imageArea\" /></div>");
                $(tab.lastChild).append("<div id=\"dropper\"></div>");
                $(tab.lastChild).append("<div class=\"button\">avaa kuvat</div>");
                tab.lastChild.lastChild.onclick = function () {pointer.renderImageList();}

                $(tab.lastChild).append("<div id=\"dropper\">PUDOTA TÄNNE!<br></div>");

                $(this).children().each(function() {
                    var src = IMG_PATH + $(this).children("is_identified_by").attr("filename");
                    $(tab.lastChild).append("<img src='" + src +  ".jpg' class='selImage' rec_id='" + $(this).attr("id") + "'/>");
                });

            } else {
     
                $(tab.lastChild).append("<div class=\"button\">edit</div>");
                tab.lastChild.lastChild.onclick = function () {pointer.record._renderSearchAddEdit(tClass, this.parentNode);}


                // list selected values
                    $(this).children().each(function() {
                        $(tab.lastChild).append("<div class='selInstance' rec_id='" + $(this).attr("id") + "'>"+ $(this).find("name").text() + "</div>");
                });
            }

        }
    }); 
}

IDA.browser.prototype.renderImageList = function () {



        // get unlinked image
        var xmlQuery = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xmlQuery = xmlQuery + "<unlinked />";

        $.post("../idacore/server/xmlin.php", { xml: xmlQuery },
        function(data){

            var div = $("#imageArea")[0];

            $(data).find("Digital_Image").each(function() {
                $(div).append('<img src="../data/thumbnails/'+ $(this).attr("filename") +'.jpg" height="100"/>');
                $(div.lastChild).addClass("ui-widget-content");
                $(div.lastChild).addClass("item2");
            });

            $("#display").sortable({
                revert: true
            });

            $(".item2").draggable({
                connectToSortable: '#imageArea',
                helper: 'clone',
                revert: 'invalid',
                zIndex: '9999',
                appendTo: 'body'
            });


           // $(div).append('<p>koe</p>');
           // $(div.lastChild).addClass("clear");
         });

            $("#browser").droppable({
                drop: function(event, ui) {
                    $(this).addClass('ui-state-highlight').find('p').html(ui.draggable.attr("id"));
                },
                over: function(event, ui) {
                    $(this).addClass('ui-state-highlight');
                    alert("over");
                },
                out: function(event, ui) {
                    $(this).removeClass('ui-state-highlight');
                }

            });


        $("#kuvat").dialog( { modal:false, title: "kuvat", width: 450, position:['right','top'] , close:function(ev,ui) {$(this).remove();}});

}

IDA.browser.prototype.renderDataEditable = function (xml, div) {

    pointer = this;

    $(xml).children("[action='input']").each(function() {
        var linkName = this.tagName;
        $(div).append("<div link=\"" +this.tagName   +  "\">"+ linkName +"</div>");
        // record id
        var recId = this.parentNode.getAttribute("id")
        var rowId = this.getAttribute("row_id");
        div.lastChild.setAttribute("rec_id",recId);
        div.lastChild.setAttribute("row_id",rowId);


	
        if(rowId) {
            if(this.tagName == "as_time-span")
                $(div.lastChild).append("<p class=\"fakelink\">"+IDA.gui.formDate(this)+"</p>");
            else
                $(div.lastChild).append("<p class=\"fakelink\">"+ pointer.renderData(this) +"</p>");

        } else {
            $(div.lastChild).append("<p class=\"fakelink\">add</p>");
        }

	// render actual form in hidden div
        pointer._renderHiddenEdit(this, div);

    });

}


IDA.browser.prototype.renderData = function (property) {

        var pointer = this;
	var s = "";
	var not_wanted = ["row_id", "table"];
	var attributes = property.attributes;

        if(this.editMode) {
            $(property).children().each(function() {
                if($(this).text() != "")
                s = s + this.tagName + ": " + $(this).text() + "<br>";
            });

        } else {

            for(var i=0; i < attributes.length; i++) {
                    if(!not_wanted.in_array(attributes[i].name) && attributes[i].value != "")
                    s = s + attributes[i].name + ": " + attributes[i].value + "<br>";
            }

        }
	return s;
}


IDA.browser.prototype._renderHiddenEdit = function(xml,div) {

    var pointer = this;

    $(div.lastChild.lastChild).click(function(event) {
        if(pointer.open_edit == false) {
            $(event.target.parentNode).children("p").toggle("slow");
            pointer.open_edit = true;
        } else {
            alert("Save your previous edit first!");
        }
    });


    // render form in hidden node
    $(div.lastChild).append("<p style=\"display:none\"  class=\"editDiv\"></p>");
    pointer.record.renderDataFields(xml, div.lastChild.lastChild);

    // buttons
    var save = IDA.gui.createButton("save");
    var cancel = IDA.gui.createButton("cancel");
    div.lastChild.lastChild.appendChild(save);
    div.lastChild.lastChild.appendChild(cancel);
    save.onclick = function () { pointer._sendData(this); return false; };
    $(cancel).click(function (event) {pointer.open_edit=false; $(event.target.parentNode.parentNode).children("p").toggle("slow"); });



}


IDA.browser.prototype._sendData = function (d) {
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
     
    var p = function () { pointer.load(pointer.id)(); };

    var ajax = new IDA.Ajax();
    ajax.sendXML(IDA.serverPath + "xmlin.php", xml, p);
}




/**
    * default renderer for a record
*/
IDA.browser.prototype.render = function (xml) {


    var pointer = this;
    this.record = new IDA.record(this);
    IDA.gui.cleanDiv(this.div);     // remove all other content

    // <root><record/><root>
    var root = xml.responseXML.firstChild.firstChild;

    this.renderTemplate(xml);
}

IDA.browser.prototype.initCurrentRecord = function (xml) {

    this.record = new IDA.record(this);
    var root = xml.responseXML.getElementsByTagName("root");
    this.record.rootRecordNode = root[0].firstChild;

}

IDA.browser.prototype.cancel = function () {

    // close pop-up
    if(this.popup)
        $(this.div).remove();
    else
        IDA.gui.cleanDiv(this.div);


}

IDA.browser.prototype.save = function (userCallBack) {

    var xml = "";


    xml =       "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    xml = xml + "<add>\n";
    xml = xml + "<" + this.record.rootRecordNode.tagName + ">\n";

    var data = this.record.getXML(this.div);
    
    xml = xml + data + "\n";  

    xml = xml + "</" + this.record.rootRecordNode.tagName + ">\n";
    xml = xml + "</add>\n";
    

    if (DEBUG) alert(xml);
    
    var pointer = this;
    if(userCallBack)
        var p = userCallBack;
    else
        var p = function(res) {pointer.afterSave(res)};
        
    var e = function (error) { alert(error); };
    var uri = IDA.serverPath + "xmlin.php";
    var query = new IDA.Ajax;

    query.sendXML(uri, xml, p, e);
}

IDA.browser.prototype.afterSave = function(response) {

    var res = response.responseXML.getElementsByTagName("response");
    var status = res[0].getAttribute("status");
    var recId = res[0].getAttribute("id");

   if(status == "ok") {


        alert("Saved OK!");

        // close pop-up
        if(this.popup) {
            $(this.div).dialog('destroy');
            $(this.div).remove();
        } else {
            IDA.gui.cleanDiv(this.div);
            this.div.appendChild(IDA.gui.createElement("h2",{},"Saved OK!"));
        }


    } else {
        var e_msg = "";
        for(var i=0; i<res[0].childNodes.length; i++) {
            e_msg = e_msg + "- " + res[0].childNodes[i].firstChild.nodeValue + "\n";
        }
        alert("ERROR\n" + e_msg);
    }


}


IDA.browser.prototype.setField = function (fieldPath, fValue, setMode) {
   
   //example path: Person::P131F.is_identified_by::firstname
   
   var splitted = fieldPath.split("::");
   if(!this.record.setField(splitted, fValue, setMode))
        alert(fieldPath + " not found!"); 
   
   
}


// callback for file drops 
IDA.browser.prototype.dragDropCallBack = function (item) {

        // file id
        var fid = item.getAttribute("rec_id");

            // check for doubles
        var count = 0;        
        
        for(var i=0; i<area.childNodes.length; i++) {
            if(area.childNodes[i].getAttribute("file_id") == fid) {
                count++;
            }
        }
        if(count > 1) {
        
            area.removeChild(area.lastChild);
            return;
            
        }   
 

        var xml = "";
    
        // was this dropped to trash bin?
        if(area.getAttribute("id") == "ida_trash_bin" && item.hasAttribute("rec_id")) {
            
            var recId = item.getAttribute("rec_id");
                
            xml =       "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
            xml = xml + "    <unlinkfile record=\"" +  recId + "\" file=\"" + fid + "\" />";
            area.removeChild(area.lastChild);
            alert(xml);
            
        } else if(area.hasAttribute("rec_id")) {
        
            var recId = area.getAttribute("rec_id");

            xml =       "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
            xml = xml + "<linkfile record=\"" +  recId + "\"";
            xml = xml + " property=\"P138B.has_representation\"";
            xml = xml + " file=\"" + fid + "\" />";


        }
        
        if(xml != "") {
            var pointer = this;
            //this.load(this.id);
            var p = function(res) { pointer.load(pointer.id); };
            var e = function (error) { alert(error); };
            var uri = IDA.serverPath + "xmlin.php";
            var ajax = new IDA.Ajax;
            alert(xml);
            ajax.sendXML(uri, xml, p, e);       
        }
    }

