<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>IDA Query Language demo</title>


<link rel="stylesheet" type="text/css" href="serverdemo.css" />

<style type="text/css">
	#add h2 { cursor: pointer; }
	#add h2:hover { color: yellow; }
</style>


 <!-- IdaBase.js is allways needed -->
<script type="text/javascript" src="../idacore/client/IdaBase.js"></script>
<script type="text/javascript" src="../idacore/client/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="../idacore/client/jquery-ui-1.7.2.custom.min.js"></script>


  <script type="text/javascript">
  $(document).ready(function(){
    $("#queries").accordion({header: 'h2'});
  });
  </script>


</head>

  <body>


<div id="outer">
    <div id="header">
        <h1>IDA XML Query API</h1>
        <p>IDA Query API is a simple, XML-based API for IDA-framework. </p>
    </div>
    <div id="add">
        <div id="queries">


            <h2>Quick search (name search)</h2>
            <div>
                <h3>&lt;quicksearch&gt;</h3>
                <ul>
                    <li onclick="quickSearch(); ">Search by names</li>
                    <p>Search everything for appellations</p>
                </ul>
                 <ul>
                    <li onclick="quickSearchPerson(); ">Search persons by names</li>
                    <p>Search limited to only persons.</p>
                </ul>
           </div>

            <h2>Search</h2>
            <div>
                <h3>&lt;search&gt;</h3>
                <ul>
                    <li onclick="allPersons(); ">Count all persons</li>
                    <p>Returns only the count of persons </p>
                    <li onclick="allPersonsResult(); ">Get all persons with
                    names and birth dates</li>
                    <p>Returns appellations and id numbers</p>
                    <li onclick="byId(); ">Get record by id</li>
                    <p>Returns a record by id</p>
                    <li onclick="byIdRes(); ">Get record by id with result set</li>
                    <p>Returns a record by id with defined properties</p>
                    <li onclick="byId_edit(); ">Get editable record by id</li>
                    <p>Returns a record by id in editable mode (more information)</p>
                    <li onclick="getPlaces(); ">Get places</li>
                    <p>Gives place hierarchy </p>


                </ul>
            </div>

            <h2>Get by id</h2>
            <div>
                <h3>&lt;get&gt;</h3>
                &lt;get&gt; returns a record only with direct data fields and without links to other instances.
                With result set also links can be included. Editable mode
                applies a template to the instance (see examples).
                <ul>
                    <li onclick="byId(); ">Get record by id</li>
                    <p>Returns a record by id</p>
                    <li onclick="byIdRes(); ">Get record by id with result set</li>
                    <p>Returns a record by id with defined properties</p>
                    <li onclick="byId_edit(); ">Get editable record by id</li>
                    <p>Returns a record by id in editable mode (more information)</p>
                    <li onclick="getPlaces(); ">Get places</li>
                    <p>Gives place hierarchy </p>


                </ul>
            </div>


            <h2>Insert</h2>
            <div>
                <h3>&lt;add&gt;</h3>
                <ul>       
                    <li onclick="insertPerson(); ">Insert Person </li>
                    <p>Inserts person with given data.</p>
                    <li onclick="insertPlace(); ">Insert Place </li>
                    <p>Inserts place in place hierarchy</p>
                </ul>
            </div>

            <h2>Edit</h2>
            <div>
                <h3>&lt;editdata&gt;</h3>
                <ul>       
                    <li onclick="editName(); ">Change name of a person </li>
                    <p>Edit certain row in certain table.</p>
                </ul>
            </div>


            <h2>Class hierarchy</h2>
            <div>
                <h3>&lt;add&gt;</h3>
                <ul>
                    <li onclick="classTree(); ">Get class tree</li>
                    <p>Class hierarchy</p>
                </ul>
                <h3>&lt;classinfo&gt;</h3>
                <ul>
                    <li onclick="classInfo(); ">Get class info</li>
                    <p>Information of a class</p>
                    <li onclick="linkInfo(); ">Get link info</li>
                    <p>Information of a link (property)</p>
                    <li onclick="addClass(); ">Insert class</li>
                    <p>Insert new user class</p>
                     <li onclick="deleteClass(); ">delete class</li>
                    <p>Delete user class by id</p>
                     <li onclick="typeList(); ">List types</li>
                    <p>List types used </p>

                </ul>
            </div>


            <h2>Templates</h2>
            <div>
                <h3>&lt;gettemplate&gt;</h3>
                <ul>
                    <li onclick="template(); ">Get template (Person)</li>
                </ul>

                <h3>&lt;addtemplate_property&gt;</h3>
                <ul>
                    <li onclick="addTemplateProperty(); ">Add property to a class.</li>
                </ul>

                <h3>&lt;removetemplate_property&gt;</h3>
                <ul>
                    <li onclick="removeTemplateProperty(); ">Remove property.</li>
                </ul>
            </div>


            <h2>Files</h2>
            <div>
                <h3>&lt;uploads&gt;</h3>
                <ul>
                    <li onclick="uploads(); ">Get unlinked files</li>
                    <p>Get uploade files that are not associated with any record.</p>
                </ul>
            </div>




	</div>
        <div id="inputArea"> </div>
        <div id="searchArea2"> </div>
        <div id="resultArea2"> </div>
    </div>

    <div id="browser">
        <p>NOTE1: You must log in if you want insert or edit stuff! </p>
        <p>NOTE2: It seems that only Firefox and Epiphany displays raw xml as plain xml.</p>
        <h2 class="base">XML-Request</h2>
        <form method="post" action="../idacore/server/xmlin.php">
        	<textarea name="xml" style="height:20em;width:100%" id="xmlarea"></textarea>
        	<p><input type="submit" value="Send and open XML file" /></p>
        </form>
        <p><input type="submit" value="Send and display XML below" onclick="send();"/></p>
        <h2>XML-Result</h2>
        <div id="resa"></div>
    </div>



    <div id="footer">
        <p>opendimension.org/ida | <a href="#" onclick="IDA.gui.openLogin(); return false; ">login</a></p>
        </div>
   </div>

</div>



<script type="text/javascript">

    function send () {

        $("#resa").empty();  
        var div = document.getElementById("xmlarea");
        var xml = div.value;

        var xmlQuery = "</?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xmlQuery = xml;


/*
var response = $($.ajax( {url: '../idacore/server/xmlin.php', async:false, dataType:"xml"} ).responseText);
alert($(response).html());
*/
         $.post("../idacore/server/xmlin.php", { xml: xmlQuery , dataType:"xml"},
             function(data){

//alert($(data.firstChild).data());

                var div = $("<div style='margin-left:1em'>");
                xmlparse(div, data.firstChild);
                $("#resa").empty();  
                $("#resa").append(div );  
            });


    function xmlparse(div, tag) {

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

            var next = $("<div style='margin-left:1em'>");
            xmlparse(next, this);
            $(div).append(next);

        });

        $(div).append("&lt;/<span>"+ tag.tagName + "</span> &gt;<br>" );  

    }
/*
        var p = function (xml) { callback(xml); };
        var e = function (error) { alert(error); };
        var ajax = new IDA.Ajax();
        ajax.sendXML('../idacore/server/xmlin.php',xml, p,e);
*/
    }


   function template () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<gettemplate title=\"Person\" />";
        div.value = xml;

   }

   function addTemplateProperty () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<addtemplate_property>\n";
        xml = xml + "  <class title=\"University_Department\">\n";
        xml = xml + "    <property title=\"P107B.is_current_or_former_member_of\" required=\"1\"  action=\"search_create\">\n";
        xml = xml + "      <class title=\"University\" />\n";
        xml = xml + "    </property>\n";
        xml = xml + "  </class>\n";
        xml = xml + "</addtemplate_property>\n";
        div.value = xml;

   }

   function removeTemplateProperty () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<removetemplate_property tid=\"ID\" />";
        div.value = xml;

   }

   function byId () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<get><record id=\"1\"/></get> ";
        div.value = xml;

   }

   function byIdRes () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<get>\n";
        xml = xml + "  <record id=\"1\"/>\n";
        xml = xml + "  <result>\n";
        xml = xml + "    <was_born >\n";
        xml = xml + "      <Birth>\n";
        xml = xml + "        <by_mother/>\n";
        xml = xml + "      </Birth>\n";
        xml = xml + "    </was_born >\n";
        xml = xml + "    <gave_birth >\n";
        xml = xml + "      <Birth>\n";
        xml = xml + "        <brought_into_life/>\n";
        xml = xml + "      </Birth>\n";
        xml = xml + "    </gave_birth >\n";
        xml = xml + "    <performed>\n";
        xml = xml + "      <Production>\n";
        xml = xml + "        <has_produced/>\n";
        xml = xml + "      </Production>\n";
        xml = xml + "    </performed>\n";
        xml = xml + "  </result>\n";
        xml = xml + "</get> ";
        div.value = xml;

   }


   function byId_edit () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<get mode=\"editable\"><record id=\"1\"/></get> ";
        div.value = xml;

   }



   function allPersons () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<search><Person /></search> ";
        div.value = xml;

   }


   function allPersonsResult () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<search><Person />";
        xml = xml + "  <result is_identified_by='name' >";
        xml = xml + "    <was_born has_time-span='start_year' />";
        xml = xml + "  </result>";
        xml = xml + "</search> ";
        div.value = xml;

   }


   function countPersons () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<search result=\"count\"><Person /></search> ";
        div.value = xml;

   }

   function personQuery () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<search><Person>\n";
        xml = xml + "<is_identified_by>\n";
        xml = xml + "  <name>Richard Stallman</name>\n";
        xml = xml + "</is_identified_by>\n";
        xml = xml + "<was_born> \n";
        xml = xml + "<Birth>\n";
        xml = xml + "<has_time-span>\n";
        xml = xml + "  <start_day>16</start_day>\n";
        xml = xml + "  <start_month>3</start_month>\n";
        xml = xml + "  <start_year>1953</start_year>\n";
        xml = xml + "</has_time-span>\n";
        xml = xml + "</Birth>\n";
        xml = xml + "</was_born>\n";
        xml = xml + "</Person></search>\n";
        div.value = xml;

   }


   function getPlaces () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<getplaces><Place id='0' /> </getplaces> ";
        div.value = xml;

   }



    function insertPerson() {
    	
        var div = document.getElementById("xmlarea");
        var xml = "<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<add>\n"; 
        xml = xml + "<Person>\n";
        xml = xml + " <P131F.is_identified_by>\n";
        xml = xml + "  <name>Richard Stallman</name>\n";
        xml = xml + " </P131F.is_identified_by>\n";
        xml = xml + " <P98B.was_born> \n";
        xml = xml + "  <Birth>\n";
        xml = xml + "   <P4F.has_time-span>\n";
        xml = xml + "    <start_day>16</start_day>\n";
        xml = xml + "    <start_month>3</start_month>\n";
        xml = xml + "    <start_year>1953</start_year>\n";
        xml = xml + "   </P4F.has_time-span>\n";
        xml = xml + "  </Birth>\n";
        xml = xml + " </P98B.was_born>\n";
        xml = xml + "</Person>\n";
        xml = xml + "</add>";

        div.value = xml;
        
    }


    function insertPlace() {
        	
        var div = document.getElementById("xmlarea");
        var xml = "<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<add>\n";
        xml = xml + "<City>\n";
        xml = xml + " <P1F.is_identified_by>\n";
        xml = xml + "  <name>Uumaja</name>\n";
        xml = xml + " </P1F.is_identified_by>\n";


        xml = xml + " <P89F.falls_within>\n";
        xml = xml + "  <Country>\n";
        xml = xml + "   <P1F.is_identified_by>\n";
        xml = xml + "    <name>Ruotsi</name>\n";
        xml = xml + "   </P1F.is_identified_by>\n";
        xml = xml + "  </Country>\n";
        xml = xml + " </P89F.falls_within>\n";
        xml = xml + "</City>\n";
        xml = xml + "</add>\n";

        div.value = xml;
}



   function quickSearch () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<quicksearch> search terms </quicksearch> ";
        div.value = xml;

   }

   function quickSearchPerson () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<quicksearch class=\"Person\"> search terms </quicksearch> ";
        div.value = xml;

   }

   function editName () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<editdata record=\"RECORD ID\" row=\"ROW ID\" link=\"PROPERTY NAME\">\n";
        xml = xml + "  <name>new name</name>\n";
        xml = xml + "</editdata>\n";
        div.value = xml;

   }
   function classTree () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<classtree title=\"CRM_Entity\" />";
        div.value = xml;

   }

   function typeList() {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<list title=\"CRM_Entity\" />";
        div.value = xml;

   }

   function classInfo () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<classinfo title=\"CRM_Entity\" />";
        div.value = xml;

   }

   function addClass () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<addclass>\n";
        xml = xml + "  <Class title=\"University\">\n";
        xml = xml + "   <subClassOf title=\"Legal_Body\" />\n";
        xml = xml + "   <comment>\n";
        xml = xml + "     This is where academics live.\n";
        xml = xml + "   </comment>\n";
        xml = xml + " </Class>\n";
        xml = xml + "</addclass>\n";
        div.value = xml;

   }

   function deleteClass () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<deleteclass id=\"ID of the class\" />\n";
        div.value = xml;

   }



   function linkInfo () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<linkinfo id=\"P1\" dir=\"F\"/>";
        div.value = xml;

   }

   function uploads () {

        var div = document.getElementById("xmlarea");
        var xml = "\<\?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        xml = xml + "<uploads />";
        div.value = xml;

   }



    // show login and login status

</script>


</body>
</html>
