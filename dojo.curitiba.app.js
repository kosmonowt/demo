/**
  * MAIN JAVASCRIPT FILE FOR CURITIBA APP
  * TESTING MODE ONLY YET
  * All new code by Andreas Kosmowicz
  * Definiert unter Anderem Rest-Schnittstellen, die mit Laravel4 erstellt wurden.
  **/

Date.prototype.yyyymmdd = function() {         
    
    var yyyy = this.getFullYear().toString();                                    
    var mm = (this.getMonth()+1).toString(); // getMonth() is zero-based         
    var dd  = this.getDate().toString();             
                        
    return yyyy + '-' + (mm[1]?mm:"0"+mm[0]) + '-' + (dd[1]?dd:"0"+dd[0]);
};

/**********/
/** DOJO **/
/**********/

dojo.require("dojo/parser");
dojo.require("dojox.form.Uploader");
dojo.require("dojo.store.JsonRest");
dojo.require("dojo.aspect");

peopleStore = new dojo.store.JsonRest({target: "api/People"});
addressesStore = new dojo.store.JsonRest({target: "api/Addresses"});
regionsStore = new dojo.store.JsonRest({target: "api/Regions"});
sucursalsStore = new dojo.store.JsonRest({target: "api/Sucursals"});
reportsStore = new dojo.store.JsonRest({target: "api/Reports"});
filesStore = new dojo.store.JsonRest({target: "api/Files"});

dojo.aspect.before(dojo.store,"query",function(m,a){loader.show();});
dojo.aspect.after(dojo.store,"query",function(m,a){loader.hide();});

var loader = {
	show : function() {$("#loader").show();},
	hide : function() {$("#loader").hide();}
};



function openTab(name) {
	$(".tab").addClass("hidden");
	$(name).removeClass("hidden");	
}

function openFormTab() {
	openTab("#forms");
}

function openReportForm() {
	addReportForm();
	openFormTab();
}

function addUsernameToHeadline(json) {
	require(["dojo/dom","dojo/dom-construct"], function(dom, domConstruct) {
		domConstruct.place("<span>&nbsp;("+json.user.username+")</span>",dom.byId("headline"));
	});
}


/**
 * Öffnet das Dashboard
 */
function openDashboard() {
	require(["dojo/dom","dojo/dom-construct","dojo/dom-class"], function(dom, domConstruct, domClass) {
		var target = dom.byId("home");
		domConstruct.empty(target);
		var lastReportsNode = domConstruct.create("div",{ innerHTML: "<h3 class='padded'>Aktuelle Berichte</h3><div id='latestReports'></div>" });
		domClass.add(lastReportsNode,"dashWidget widgetReports");
		domConstruct.place(lastReportsNode,target);
		getLatestReports();
		
		var newReportNode = domConstruct.create("div",{ innerHTML: "<h3 class='padded'><a href='#newReport' onclick='openReportForm()'>Neuer Bericht</a></h3>" });
		domClass.add(newReportNode,"dashWidget widgetNewReport");
		domConstruct.place(newReportNode,target);
				
	});
}

function bindNavigateRight(className) {
	$(className).on("click",function(event){
		var to = $(this).data("to");
		var id = $(this).data("id");
		var content = $(this).data("content");
		openTab(to);
		eval (content+"("+id+")");
	});	
}

/**
 * Erstellt eine Tabelle mit Regionen
 */
function showRegions() {
	loader.show();
	require(["dojo/dom","dojo/dom-construct","dojo/dom-class"], function(dom, domConstruct, domClass) {
		var target = dom.byId("regionsTable");
		regionsStore.query().then(function(result){
			var html;
			for (var i = 0; i < result.length; i++) {
				html = '<li class="table-view-cell media">'+
			    		'<a class="navigate-right navigate-regions" data-id="'+result[i].id+'" data-to="#sucursals" data-content="showSucursals">'+
			    		'<div class="media-body">'+result[i].name+'</div></a></li>';
				domConstruct.place(html,target);
			}
			bindNavigateRight(".navigate-regions");
			loader.hide();
		});
	});
}

/**
 * Zeigt eine Liste mit Filialen (zu einer Region)
 * @param id
 */
function showSucursals(id) {
	loader.show();
	require(["dojo/dom","dojo/dom-construct","dojo/dom-class"],function(dom, domConstruct, domClass) {
		var target = dom.byId("sucursalsTable");
		domConstruct.empty(target);
		regionsStore.get(id).then(function(result){
			var html;
			if (result.length == 0) {
				html = '<li class="table-view-cell media">'+
	    		'<a class="navigate-sucursal_reports">'+
	    		'<div class="media-body">Keine Filialen in dieser Region.</div></a></li>';
				domConstruct.place(html,target);
			} else
			for (var i = 0; i < result.length; i++) {
				html = '<li class="table-view-cell media">'+
			    		'<a class="navigate-right navigate-sucursal_reports" data-id="'+result[i].id+'" data-to="#sucursal_reports" data-content="showSucursalReports">'+
			    		'<div class="media-body">'+result[i].lfdNr+' - '+result[i].zip+' '+result[i].city+'</div></a></li>';
				domConstruct.place(html,target);
			}
			bindNavigateRight(".navigate-sucursal_reports");
			loader.hide();
		});		
	});
}

/**
 * Generates the Report's title
 */
function reportTitleString(report) {
	return report.Sucursal.lfdNr+'_'+report.date;
}

/**
 * Returns a string with a report
 */
function reportEntry(report) {
	return  '<li class="table-view-cell media">'+
			'<a class="navigate-right navigate-reports" data-id="'+report.id+'" data-to="#reportDetails" data-content="showReportDetails">'+
			'<div class="media-body">'+reportTitleString(report)+'</div></a></li>';
}

function getLatestReports() {
	require(["dojo/dom","dojo/dom-construct"], function(dom,domConstruct){
		var target = dom.byId("latestReports");
		reportsStore.query("?count=2",{}).then(function(result){
			var html;
			for (var i = 0; i < result.length; i++) {
				html = reportEntry(result[i]);
				domConstruct.place(html,target);
			}			
			bindNavigateRight(".navigate-reports");			
		});
	});
}

/**
 * Erstellt eine Tabelle mit Berichten
 */
function showReports() {
	loader.show();
	require(["dojo/dom","dojo/dom-construct","dojo/dom-class","dojo/date/locale"], function(dom, domConstruct, domClass,locale) {
		var target = dom.byId("reportsTable");
		reportsStore.query().then(function(result){
			var html;
			for (var i = 0; i < result.length; i++) {
				html = reportEntry(result[i]);
				domConstruct.place(html,target);
			}
			bindNavigateRight(".navigate-reports");
			loader.hide();
		});
	});
}

/**
 * Erstellt eine Tabelle mit Berichten zu einer Filiale
 */
function showSucursalReports(id) {
	loader.show();
	require(["dojo/dom","dojo/dom-construct","dojo/dom-class","dojo/date/locale"], function(dom, domConstruct, domClass,locale) {
		var target = dom.byId("sucursalReportsTable");
		domConstruct.empty(target);		
		reportsStore.query("?sucursal_id="+id).then(function(result){
			var html;
			if (!result.length) {
				domConstruct.place('<li class="table-view-cell media"><div class="media-body">Keine Berichte vorhanden</div></li>',target);
			} else {
				for (var i = 0; i < result.length; i++) {
					html = reportEntry(result[i]);
					domConstruct.place(html,target);
				}
				bindNavigateRight(".navigate-reports");
			}
			domConstruct.place('<li class="table-view-cell media"></li>'+"<li class='table-view-cell media'>"+
					'<a class="navigate-right">'+
					'<span class="media-object pull-left icon icon-edit"></span>'+
					'<div class="media-body" id="editSucursal" data-id="'+id+'" data-form="editSucursalForm">'+
			        'Filaldetails bearbeiten'+
			        "</div></a></li>",target);
			$("#editSucursal").on("click",function(event){
				openTab("#forms");
				eval ($(this).data("form")+"("+id+")");
			});
			loader.hide();
		});
	});
}



function showReportDetails(id) {
	require(["dojo/dom","dojo/dom-construct","dojo/dom-class","dojo/date/locale",'dojo/on','dojo/dom-form','dojo/request'], function(dom, domConstruct, domClass,locale,on,domForm,request) {
		loader.show();
		var target = dom.byId("reportDetails");
		reportsStore.get(id).then(function(report){
			domConstruct.empty(target);
			var html;
			html = 	'<div id="reportDetailsDetails" data-id="'+id+'">'+
  					'<form id="reportMailForm" method="post" action="api/Reports/email/'+report.id+'">'+
					'<h2>'+report.time_period+': '+reportTitleString(report)+'</h2>'+
					'<div class="tableWrapper"><table class="reportDetailsTable"><tr>'+
					'<td class="reportTableDescription">Anwesend&nbsp;<span class="icon icon-edit" id="editReportAttendees"></span></td>'+
					'<td class="reportTableDetail" id="reportAttendeesField">'+report.attendee_names+'</td>'+
					'</tr><tr>'+
					'<td class="reportTableDescription">Berichtdetails&nbsp;<span class="icon icon-edit" id="editReportDetails"></span></td>'+
					'<td class="reportTableDetail" id="reportDetailsField">'+report.details+'</td>'+
					'</tr><tr>'+
					'<td class="reportTableDescription">Anzahl Bilder</td>'+
					'<td class="reportTableDetail">'+report.files.length;
			html += (report.files.length) ? ' <a href="showImages" id="reportImages'+report.id+'">(Bilder Ansehen)</a>' : "</td>";
			html += '</tr></table></div><div id="imageContainer'+report.id+'"></div>'+
					'<div class="actions">'+
  					'<a class="btn btn-primary btn-block btn-outlined" href="api/Reports/pdf/'+report.id+'" target="_blank" onclick="window.open(\'api/Reports/pdf/'+report.id+'\',\'_blank\');">PDF Herunterladen</a>'+
  					// '<button id="reportMailPdf" class="btn btn-primary btn-block btn-outlined" >PDF per E-Mail versenden</button>'+
					// '<ul class="table-view">'+
//  					'<li class="table-view-cell">'+
  			// 		'Sende Bericht an Filialleiter<div class="toggle active" data-input="send_to_manager" onclick="processToggle(\'send_to_manager\')"><div class="toggle-handle"></div></div></li>'+
  			// 		'<li class="table-view-cell">'+
  			// 		'Sende Bericht an Stellv. Filialleiter<div class="toggle" data-input="send_to_vice_manager" onclick="processToggle(\'send_to_vice_manager\')"><div class="toggle-handle"></div></div></li>'+
					// '<li class="table-view-cell">'+  					
  			// 		'Sende Bericht an Thekenkraft<div class="toggle" data-input="send_to_sales_responsible" onclick="processToggle(\'send_to_sales_responsable\')"><div class="toggle-handle"></div></div></li>'+
					// '<li class="table-view-cell">'+  					
  			// 		'Kopie an mich senden<div class="toggle" data-input="send_to_myself" onclick="processToggle(\'send_to_myself\')"><div class="toggle-handle"></div></div></li>'+  					
  			// 		'</ul>'+
//  					'<input type="hidden" name="send_to_manager" id="send_to_manager" value="1">'+
//  					'<input type="hidden" name="send_to_vice_manager" id="send_to_vice_manager" value="0">'+
//  					'<input type="hidden" name="send_to_sales_responsible" id="send_to_sales_responsible" value="0">'+
//  					'<input type="hidden" name="send_to_myself" id="send_to_myself" value="0">'+
  					'</form>'+
  					'</div></div>';
			domConstruct.place(html,target);
			loader.hide();
			var reportImageViewButton = dom.byId("reportImages"+report.id);
			if (reportImageViewButton !== null) {				
				on.once(reportImageViewButton,"click",function(event){
					event.stopPropagation;
					event.preventDefault;
					for (var j = 0;j < report.files.length; j++) {
						domConstruct.place("<img style='width:100%' src='image/480/"+report.files[j].file_id+"'>",dom.byId("imageContainer"+report.id));
					}
	
				});
			}

			var editReportDetailsButton = dom.byId("editReportDetails");			
			on.once(editReportDetailsButton,"click",function(event){
				
				var target = dom.byId("reportDetailsField");
				var tmp = document.createElement("DIV"); // needed to split up html to text
			    tmp.innerHTML = report.details; // html2text
				
			    domConstruct.place(
						'<td class="reportTableDetail" id="reportDetailsField">'+"<form id='editReportDetailsForm'>" +
						"<textarea id='formReportDetails' name='details' rows='15'>"+
						(tmp.textContent || tmp.innerText || "")+ //html2text
						"</textarea>"+
						"<button type='btn btn-primary btn-block'>Speichern</button>"+
						"</form></td>"
						,target,"replace");
				
			    on(dom.byId("editReportDetailsForm"),"submit", function(event){
					event.stopPropagation();
					event.preventDefault();
					request.post("api/Reports/"+report.id+"/detail",{
						data: domForm.toObject("editReportDetailsForm"),
						timeout : 20000,
						handleAs : "json"
					}).then(function(response){
						var message;
						if (typeof(response.error) !== "undefined") {
							message = "<h3 class='error'>"+response.error.message+"</h3>";
						} else {
							message = response.message;
						}
						
						domConstruct.place('<td class="reportTableDetail" id="reportDetailsField">'+message+'</td>',dom.byId("reportDetailsField"),"replace");
						
					});
				});				
			});			
			
			var editReportAttendeesButton = dom.byId("editReportAttendees");			
			on.once(editReportAttendeesButton,"click",function(event){
				
				var target = dom.byId("reportAttendeesField");			
			    domConstruct.place(
						'<td class="reportTableDetail" id="reportAttendeesField">'+"<form id='editReportAttendeesForm'>" +
						"<input id='formReportAttendees' name='attendees'>"+
						"</textarea>"+
						"<button type='btn btn-primary btn-block'>Speichern</button>"+
						"</form></td>"
						,target,"replace");
				
			    on(dom.byId("editReportAttendeesForm"),"submit", function(event){
					event.stopPropagation();
					event.preventDefault();
					request.post("api/Reports/"+report.id+"/attendee",{
						data: domForm.toObject("editReportAttendeesForm"),
						timeout : 20000,
						handleAs : "json"
					}).then(function(response){
						var message;
						if (typeof(response.error) !== "undefined") {
							message = "<h3 class='error'>"+response.error.message+"</h3>";
						} else {
							message = response.message;
						}
						
						domConstruct.place('<td class="reportTableDetail" id="reportAttendeesField">'+message+'</td>',dom.byId("reportAttendeesField"),"replace");
						
					});
				});				
			});
			
			
			var form = dom.byId("reportMailForm");
			on(form,"submit",function(event){
				event.stopPropagation();
				event.preventDefault();
				request.post('api/Reports/email/'+report.id,{
					data: domForm.toObject("reportMailForm"),
					timeout : 20000,
					handleAs : "json"
				}).then(function(response){
					var target = dom.byId("reportDetailsDetails");
					var message;
					if (typeof(response.error) !== "undefined") {
						message = "<h3 class='error'>"+response.error.message+"</h3>";
					} else {
						message = response.message;
					}
					domConstruct.place(message,target);
				});
			});
		});
	});
}

/**
 * Generelle funktion zur Erstellung eines Formulares im #forms Div
 * @param options
 */
function addForm(options) {

	require(["dojo/dom","dojo/dom-construct","dojo/on","dojo/dom-form","dijit/form/FilteringSelect","dojo/query","dijit/registry","dojox/form/Uploader","dojo/date/locale","dojo/_base/declare","dijit/form/DateTextBox"], function(dom, domConstruct, on, domForm, FilteringSelect, query,registry, uploader,locale,declare,DateTextBox){

		if (typeof(options.target) == "undefined") options.target = "forms";
		var target = dom.byId(options.target);
		var panelName = options.name+'Panel';

		if (query("#"+options.target+" .addFormPanel").length) {

			query("#"+options.target+" .addFormPanel").style("display","none");
			query("#"+panelName).style("display","block");
		}

		if (!query("#"+panelName).length) {

			//domConstruct.empty(target);
			var files = options.files ? ' enctype="multipart/form-data"' : "";
			var html = 
			'<div id="'+panelName+'" class="addFormPanel"><h2>'+options.heading+'</h2><form id="'+options.name+'"'+files+'>' +
			options.content +
			'<button class="btn btn-block">'+options.button+'</button></form></div>';
			domConstruct.place(html,target);
			
			formElement = dom.byId(panelName);
			
			if (typeof(options.select) !== "undefined") {
				var select;
				var current;
				for (var i = 0; i < options.select.length; i++) {
					current = options.select[i];
					select = registry.byId(current.element);
					
					select = new FilteringSelect({
						id: current.id,
			            name: current.name,
			            placeHolder: "Bitte wählen...",
			            store: current.store,
			            onChange: current.onChange ? current.onChange : function(val) {}
			        }, current.element);
		        
		        select.startup();
				}
			}
			
			if (typeof(options.upload) !== "undefined") {
				var uploadWidget = new uploader(options.upload);
				dojo.byId(options.upload.id).appendChild(uploadWidget.domNode);
			}
			
			if (typeof(options.dateTextBox) !== "undefined") {
	            var date = new DateTextBox({
	                value: new Date(),
	            	onChange: function(val) {
	            		$("#"+options.dateTextBox[0].element).val(val);// Make foreach once we need more of them!
            		}
	            }, "datetextbox");
	            date.startup();
			}
			
			var form = dom.byId(options.name);
			on(form,"submit",options.submit ? options.submit : function(event){
				event.stopPropagation();
				event.preventDefault();
				options.store.add(domForm.toObject(options.name));
				form.reset();
				openTab("#settings");

			});

			if (typeof(options.callback) != "undefined") options.callback();
			
		}
	});
}


/**
 * Collection of Standard Form Element for reuse.
 */
filterFormElements = {
	region : function(prefix) {return '<label for="'+prefix+'_region_id">Region</label><div id="'+prefix+'_filter_region_id"></div>'},
	sucursal : function(prefix) {return '<label for="'+prefix+'_sucursal_id">Filiale</label><div id="'+prefix+'_filter_sucursal_id"></div>'},
	report : function(prefix) {return '<label for="'+prefix+'_report_id">Bericht</label><div id="'+prefix+'_filter_report_id"></div>'}
};

function editSucursalForm(id) {
	sucursalsStore.query("/"+id).then(function(sucursal){
		$("#editSucursalFormPanel").remove();
		addForm({
			"name" : "editSucursalForm",
			"heading" : "Filiale bearbeiten",
			"button" : "Filiale bearbeiten",
			"store" : sucursalsStore,
			"content" : '<label for="lfdNr">Filial Nr.</label><input type="text" name="lfdNr" value="'+sucursal.lfdNr+'">'+
			'<input type="hidden" name="id" value="'+sucursal.id+'">'+
//			filterFormElements.region("sucursalForm") + 
			'<label for="address.street">Straße</label><input type="text" name="address_street" value="'+sucursal.address.street+'">'+
			'<label for="address.street2">Zusatz</label><input type="text" name="address_street2" value="'+sucursal.address.street2+'">'+
			'<label for="address.zip">PLZ</label><input type="text" name="address_zip" value="'+sucursal.address.zip+'">'+
			'<label for="address.city">Stadt</label><input type="text" name="address_city" value="'+sucursal.address.city+'">'+
			'<label for="address.country">Land</label><input type="text" name="address_country" value="'+sucursal.address.country+'">'+
			'<label for="address.tel">Tel</label><input type="text" name="address_tel" value="'+sucursal.address.tel+'">'+
			'<label for="address.fax">Fax</label><input type="text" name="address_fax" value="'+sucursal.address.fax+'">'+
			'<label for="address.email">E-Mail</label><input type="text" name="address_email" value="'+sucursal.address.email+'">'
		});
	});
}


function addSucursalForm() {
	addForm({
		"name" : "addSucursalForm",
		"heading" : "Filiale hinzufügen",
		"button" : "Filiale hinzufügen",
		"store" : sucursalsStore,
		// "select" : [
		//             {"id" : "sucursalForm_region_id", "name" : "region_id","element":"sucursalForm_filter_region_id","store" : regionsStore},
		//             {"id" : "sucursalForm_manager_id", "name" : "manager_id","element":"sucursalForm_filter_manager_id","store" : peopleStore},
		//             {"id" : "sucursalForm_vice_manager_id", "name" : "vice_manager_id","element":"sucursalForm_filter_vice_manager_id","store" : peopleStore},
		//             {"id" : "sucursalForm_sales_responsible_id", "name" : "sales_responsible_id","element":"sucursalForm_filter_sales_responsible_id","store" : peopleStore}
		//             ],
		"content" : '<label for="lfdNr">Filial Nr.</label><input type="text" name="lfdNr">'+
		filterFormElements.region("sucursalForm") + 
		// '<label for="manager_id">Filialleiter</label><div id="sucursalForm_filter_manager_id"></div>'+
		// '<label for="vice_manager_id">Stellv. Filialleiter</label><div id="sucursalForm_filter_vice_manager_id"></div>'+
		// '<label for="sales_responsible_id">Verantwortliche Thekenkraft</label><div id="sucursalForm_filter_sales_responsible_id"></div>'+
		'<label for="address.street">Straße</label><input type="text" name="address_street">'+
		'<label for="address.street2">Zusatz</label><input type="text" name="address_street2">'+
		'<label for="address.zip">PLZ</label><input type="text" name="address_zip">'+
		'<label for="address.city">Stadt</label><input type="text" name="address_city">'+
		'<label for="address.country">Land</label><input type="text" name="address_country">'+
		'<label for="address.tel">Tel</label><input type="text" name="address_tel">'+
		'<label for="address.fax">Fax</label><input type="text" name="address_fax">'+
		'<label for="address.email">E-Mail</label><input type="text" name="address_email">'
	});	
}

function addPersonForm() {
	addForm({
		"name" : "addPersonForm",
		"heading" : "Person hinzufügen",
		"button" : "Hinzufügen",
		"store" : peopleStore,
		"content" : '<label for="vorname">Vorname</label><input type="text" name="vorname">'+
		'<label for="nachname">Nachname</label><input type="text" name="nachname">'+
		'<label for="address.street">Straße</label><input type="text" name="address_street">'+
		'<label for="address.street2">Zusatz</label><input type="text" name="address_street2">'+
		'<label for="address.zip">PLZ</label><input type="text" name="address_zip">'+
		'<label for="address.city">Stadt</label><input type="text" name="address_city">'+
		'<label for="address.country">Land</label><input type="text" name="address_country">'+
		'<label for="address.tel">Tel</label><input type="text" name="address_tel">'+
		'<label for="address.fax">Fax</label><input type="text" name="address_fax">'+
		'<label for="address.email">E-Mail</label><input type="text" name="address_email">'
	});
}

function addReportForm() {
	addForm({
		"name" : "addReportForm",
		"heading" : "Neuen Bericht erstellen",
		"button" : "Speichern",
		"files" : true,
		"store" : reportsStore,
		"select" : [
		            //{"id" :"reportForm_attendee_id", "name" : "attendee_id","element":"reportForm_filter_attendee_id","store":peopleStore},
		            {"id" :"reportForm_sucursal_id", "name" : "sucursal_id","element":"reportForm_filter_sucursal_id","store":sucursalsStore}
		            ],
		"dateTextBox" : [
		            {"id" : "reportForm_date", "name":"date","element":"reportForm_input_date"}
	                ],
//		"upload" : {"id" : "upload_files", "multiple":true,"uploadOnSelect":true,"url":"file/upload","label":"Fotos"},
		"content" : '<label for="date">Datum</label><input type="datetime" name="datetextbox" id="datetextbox" /><input type="hidden" value="'+new Date()+'" name="date" id="reportForm_input_date" />'+
		'<label for="time_period">Zeitraum</label><input type="text" name="time_period" />'+
		filterFormElements.sucursal("reportForm")+
		'<label for="attendee_names">Anwesende Fachkraft</label><input type="text" name="attendee_names" />'+
		'<label for="attendee_names">Anwesende Führungskraft</label><input type="text" name="manager_names" />'+
		//'<label for="manager_id">Führung</label><div id="reportForm_filter_manager_id">Wird automatisch gesetzt.</div>'+
		'<label for="details">Bericht</label><textarea name="details" rows="5"></textarea>'+
//		'<label for="files">Bilder hinzufügen</label><div id="upload_files"></div>'+
		''
	});
}

function addFileForm() {
	addFileFormFiles = [];
	addForm({
		"target" : "files",
		"name" : "addFileForm",
		"heading" : "Neue Datei Hinzufügen",
		"button" : "Hochladen",
		"submit" : function(event) {
  				event.stopPropagation();
			    event.preventDefault();
 
			    var formObj = $("#addFileForm");
			    var reportId = parseInt($("#addFileForm input[name='report_id']").val());
			    console.log(reportId);
			    if (reportId >= 1) {
	 			    var formURL = "api/Files/upload/"+reportId;
				    var formData = new FormData(this);
				    $.ajax({
				        url: formURL,
				    	type: 'POST',
				        data:  formData,
				    	mimeType:"multipart/form-data",
				    	contentType: false,
				        cache: false,
				        processData:false,
					    success: function(data, textStatus, jqXHR)
					    {
					 		// console.log(data);
					 		// console.log(textStatus);
					 		// console.log(jqXHR);
					 		alert("Datei erfolgreich hochgeladen.");
					    }
					}); 
			    }
			},
		"files" : true,
		"store" : filesStore,
		"select" : [
			{	
				"id" : "fileForm_sucursal_id",
				"name" : "sucursal_id",
				"element":"fileForm_filter_sucursal_id",
				"store":sucursalsStore
			},
			{	
				"id" : "fileForm_report_id",
				"name" : "report_id",
				"element":"fileForm_filter_report_id",
				"store" :reportsStore,
				"onChange" : function(val){
					dijit.byId("fileForm_report_id").set("value",this.item ? this.item.sucursal_id : null)
				}

			}
			],
		"content" : /*filterFormElements.sucursal("fileForm")+*/
					filterFormElements.report("fileForm")+
					'<input name="images" multiple="true" type="file" id="images" />'
	});
}

function toggleTabs(activate) {
	require(["dojo/dom","dojo/dom-class","dojo/query"], function(dom,domClass,query) {
		if (activate) {	query(".tab-item").removeClass("blocked"); } 
		else { query(".tab-item").addClass("blocked");}
	});
}

function processToggle(name) {
	$("div[data-input='"+name+"']").toggleClass('active');
	var f = $('#'+name); // Get inputfield to modify
	var val = parseInt(f.val());
	val = (val + 1) % 2;
	f.val(val);
}

function initApp(response) {
	addUsernameToHeadline(response);
	openDashboard(response);
	toggleTabs(true);
}

require([
		'dojo/dom',
		'dojo/dom-construct',
		'dojo/on',
		'dojo/dom-form',
		'dojo/request',
         ], function(dom, domConstruct, on, domForm, request){
	toggleTabs(false);
	loader.show();
	request("index.php",{handleAs:"json"}).then(
		function(response){
			if (!response.authenticated) {
				// Authenticate First
				domConstruct.place(
						"<div id='loginPanel'>"+
						"<h2>Bitte einloggen:</h2>"+
						"<form method='post' action='index.php' id='loginForm'>"+
						"<input type='text' name='username'>"+
						"<input type='text' name='password'>"+
						"<button class='btn btn-block'>Login</button>"+
						"</form>"+
						"</div>"
						,dom.byId("home"));
				form = dom.byId("loginForm");
				on(form,"submit",function(event){
					event.stopPropagation();
					event.preventDefault();
					
					request.post("index.php",{
						data: domForm.toObject("loginForm"),
						timeout : 2000,
						handleAs : "json"
					}).then(function(response){
						var target = dom.byId("home");
						if (response.authenticated) {
							initApp(response);
						} else {
							domConstruct.place("<h3>Login fehlgeschlagen, bitte erneut versuchen.</h3>",target,"first");
						}
					});
				});
			} else {
				initApp(response);
			}
			loader.hide();
			},
		function(error){
			console.log("An error occurred: " + error);
		}
	);
});

