var modalWidth = 500;

function createMenuBar_css(adminPath, module, menuSectionName)
{
	// This function replaces 'createMenuBar()', which was large and clunky.
	// We should be able to streamline the process considerably using a 
	// light-weight, standards-oriented menu bar.
	
	// First, retrieve the menu items
	menuRequest_css=getXmlHttpObject();
	if (menuRequest_css==null)
	{
		alert("Your browser is not compatible with the AJAX functions of this application. Please upgrade your browser to the most current version. If you do not know where to find a current browser, please visit http://www.getfirefox.com .");
		return;
	}
	
	menuRequestUrl_css = adminPath+'/modules/menu_css/getMenu.php?module='+module+'&random='+Math.random();
	menuRequest_css.onreadystatechange = function()
	{
		loadMenu_css(menuSectionName);
	}
	menuRequest_css.open("GET",menuRequestUrl_css,true);
	menuRequest_css.send(null);
}
function is_object(mixed_var)
{
	if(mixed_var instanceof Array)
	{
		return false;
	}
	else
	{
		return (mixed_var !== null) && (typeof( mixed_var ) == 'object');
	}
}
function loadMenu_css(menuSectionName)
{
	if (menuRequest_css.readyState==4 || menuRequest_css.readyState=="complete")
	{
		if (menuRequest_css.status==200)
		{
			menuResponseHTML = menuRequest_css.responseText;
			document.getElementById('leftbar').innerHTML = menuResponseHTML;
			showMenu_css(menuSectionName);
		}
	}
}

function showMenu_css(menuName)
{
	hideAllMenus_css();
	document.getElementById('menu_'+menuName).className = 'show';
}

function hideAllMenus_css()
{
	theMenu =document.getElementById('menubuttons_ul');
	var theLists = theMenu.getElementsByTagName('ul');
	
	for (k=0; k<theLists.length; k++)
	{
		document.getElementById(theLists[k].getAttribute('id')).className='';
	}
}

function betterInnerHtml(element, HTML, clearfirst)
{
	// Load the HTML as XML
	function Load(xmlString)
	{
		var xml;
		if (typeof DOMParser != "undefined")
		{
			xml = (new DOMParser()).parseFromString(xmlString, "application/xml");
		}
		else
		{
			var ieDOM = ["MSXML2.DOMDocument", "MSXML.DOMDocument", "Microsoft.XMLDOM"];
			for (var i=0; i<ieDOM.length && !xml; i++)
			{
				try
				{
					xml = new ActiveXObject(ieDOM[i]);
					xml.loadXML(xmlString);
				}
				catch(e)
				{}
			}
		}
		return xml;
	}
	
	// Recursively copy the XML into the Dom
	function Copy(domNode, xmlDoc, level)
	{
		if (typeof level == "undefined")
		{
			level = 1;
		}
		if (level > 1)
		{
			if (xmlDoc.nodeType == 1)
			{
				// Element node
				var thisNode = document.createElement(xmlDoc.nodeName);
				
				// Attributes
				for (var a=0, attr = xmlDoc.attributes.length; a<attr; a++)
				{
					var aName = xmlDoc.attributes[a].name, aValue = xmlDoc.attributes[a].value;
					switch (aName)
					{
						case "class":
							thisNode.className = aValue;
							break;
						case "for":
							thisNode.htmlFor = aValue;
							break;
						default:
							thisNode.setAttribute(aName, aValue);
					}
				}
				
				// Append node
				domNode = domNode.appendChild(thisNode);
			}
			else if (xmlDoc.nodeType == 3)
			{
				// Text node
				var text = (xmlDoc.nodeValue ? xmlDoc.nodeValue : "");
				var test = text.replace(/^\s*|\s*$/g, "");
				
				// Corrected the following line to account for strings of length 2.
				//if (test.indexOf("<!--") != 0 && test.indexOf("-->") != (test.length -3))
				if (test.length < 3 || (test.indexOf("<!--") != 0 && test.indexOf("-->") != (test.length - 3)))
				{
					domNode.appendChild(document.createTextNode(text));
				}
			}
		}
		
		// Do child nodes
		for (var i=0, j=xmlDoc.childNodes.length; i<j; i++)
		{
			Copy(domNode, xmlDoc.childNodes[i], level+1);
		}
	}
	
	// Load the XML and copies to DOM
	HTML = "<root>"+HTML+"</root>";
	var xmlDoc = Load(HTML);
	if (element && xmlDoc)
	{
		if (clearfirst != false)
		{
			while (element.lastChild)
			{
				element.removeChild(element.lastChild);
			}
		}
		Copy(element, xmlDoc.documentElement);
	}
}

function createMenuBar(selectedMenu, module, adminPath, totalPageHeight)
{
	targetTable = document.getElementById("leftbar");
	
	betterInnerHtml(targetTable.tBodies[0], '<tr><td></td></tr>', true);
	
	if (isDefined(selectedMenu) && selectedMenu != null)
	{
		currentMenu = selectedMenu;
	}
	else
	{	
		if (module)
		{
			// Open a backend connection to determine the menu section of the module
			reqMenuSection=getXmlHttpObject();
			if (reqMenuSection==null)
			{
				alert("Your browser is not compatible with the AJAX functions of this application. Please upgrade your browser to the most current version. If you do not know where to find a current browser, please visit http://www.getfirefox.com .");
				return;
			}
	
			var requesturl = adminPath + '/modules/main/getMenuSectionId.php?moduleid='+module;
	
			reqMenuSection.onreadystatechange = function()
			{
				handleMenuSectionResponse(module, adminPath);
			}
			reqMenuSection.open("GET",requesturl,true);
			reqMenuSection.send(null);
		}
		else
		{
			module = null;
			currentMenu = arrMenuHeadings[0].id;
		}
	}
	
	// Determine the array index of the currently-selected menu
	for (j=0; j<arrMenuHeadings.length; j++)
	{
		if (typeof(currentMenu) != 'undefined' && currentMenu == arrMenuHeadings[j].id)
		{
			currentMenuIndex = j; // Set the global variable
		}
	}
	
	for(i=0; i<arrMenuHeadings.length; i++)
	{
		newRow = document.createElement('tr');
		newRow.onclick = new Function("createMenuBar(" + arrMenuHeadings[i].id + ", "+module+", '"+adminPath+"', "+totalPageHeight+")");
		newCell = document.createElement('td');
		newCell.className = 'menuHeading';
		newSpan = document.createElement('span');
		newSpan.className = 'section_title';
		betterInnerHtml(newSpan, arrMenuHeadings[i].displayName, true);
		
		newCell.appendChild(newSpan);
		newRow.appendChild(newCell);
		
		targetTable.tBodies[0].appendChild(newRow);
		//targetTbody.appendChild(newRow);
		
		if (typeof(currentMenu) != 'undefined' && currentMenu == arrMenuHeadings[i].id)
		{
			// Create the larger cell to hold the sub-menu items
			submenuDiv = document.createElement('div');
			submenuDiv.setAttribute('id','activeSubMenu');
			
			submenuDivList = document.createElement('ul');
			submenuDivList.setAttribute('id', 'activeSubMenuList');
			
			submenuDiv.appendChild(submenuDivList);
			
			scrollContainer = document.createElement('div');
			scrollContainer.setAttribute('id', 'ScrollContainer');
			
			scrollContainer.appendChild(submenuDiv);
			
			submenuCell = document.createElement('td');
			submenuCell.setAttribute('id', 'tdActive');
			submenuCell.setAttribute('valign', 'top');
			
			submenuRow = document.createElement('tr');
			
			//submenuCell.appendChild(submenuDiv);
			submenuCell.appendChild(scrollContainer);
			submenuRow.appendChild(submenuCell);
			targetTable.tBodies[0].appendChild(submenuRow);
			
			// Create a connection to retrieve the menu elements for this menu section
			xmlHttp=getXmlHttpObject();
			if (xmlHttp==null)
			{
				alert("Your browser is not compatible with the AJAX functions of this application. Please upgrade your browser to the most current version. If you do not know where to find a current browser, please visit http://www.getfirefox.com .");
				return;
			}
	
			var requesturl = adminPath+'/modules/menu/getMenuItems.php?sectionid='+arrMenuHeadings[i].id;
	
			xmlHttp.onreadystatechange = function()
			{
				loadMenuItems(adminPath, totalPageHeight);
			}
			xmlHttp.open("GET",requesturl,true);
			xmlHttp.send(null);
		}
	}
}

function getXmlHttpObject()
{
	var objXMLHttp=null;
	if (window.XMLHttpRequest)
	{
		objXMLHttp=new XMLHttpRequest();
	}
	else if (window.ActiveXObject)
	{
		objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	return objXMLHttp;
}

function handle_error(errorObject)
{
	// Handle the JSON error object that was returned
	var errorCode = errorObject.error_number;
	var errorMessage = errorObject.message.replace(/<br \/>/g, '\n');
	var errorOutput = "Error "+errorCode+":<br style='clear: none' />"+errorMessage;
	alert("Error "+errorCode+": "+errorMessage);
	//document.getElementById("error_output").innerHTML = errorOutput;
	//document.getElementById("error_output").className = "show";
}

function handleMenuSectionResponse(module, adminPath)
{
	if (reqMenuSection.readyState==4 || reqMenuSection.readyState=="complete")
	{
		if (reqMenuSection.status==200)
		{
			var responseObject = reqMenuSection.responseText;
			
			// See if there is an error we need to handle
			if (typeof(responseObject.error) == 'object')
			{
				handle_error(responseObject.error);
			}
			else
			{
				createMenuBar(responseObject, module, adminPath);
			}
		}
		else
		{
			if (reqMenuSection.status == 404)
			{
				alert("Request failed because the target script does not exist.");
			}
			else if (reqMenuSection.status == 503)
			{
				alert("Request failed because the target script permissions make it inaccessible.");
			}
			else
			{
				alert("Request failed with HTTP Response Code "+reqMenuSection.status);
			}
		}
	}
}

function hideError()
{
	errorDiv = document.getElementById('errorPane');
	errorDivText = document.getElementById('errorPaneText');
	
	betterInnerHtml(errorDivText, '', true);
	errorDiv.style.display = 'none';
}

function isDefined(variable)
{
	return (!(!(variable||false)))
}

function jsDhMessage(messageText, messageType, autoHideMessage)
{
	errorDiv = document.getElementById('errorPane');
	errorDivText = document.getElementById('errorPaneText');
	
	if (messageType == 'info' || messageType == 'information')
	{
		errorDiv.className = 'information';
	}
	
	betterInnerHtml(errorDivText, messageText, true);
	errorDiv.style.display = 'block';

	if (autoHideMessage == true)
	{
		setTimeout('$(\'#errorPane\').hide(500)', 4000 + (1000 * (messageText.length/20))); // Read at twenty characters per second, plus one second.
	}
}

function loadMenuItems(adminPath, totalPageHeight)
{
	if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete")
	{
		if (xmlHttp.status==200)
		{
			var responseObject = eval('('+xmlHttp.responseText+')');
			
			// See if there is an error we need to handle
			if (typeof(responseObject.error) == 'object')
			{
				handle_error(responseObject.error);
			}
			else
			{
				// An JSON object was returned, so display the results
				
				// Prepare the output location to receive data
				outputLocation = document.getElementById("activeSubMenuList");
				
				// Figure out the maximum number of list items that can be displayed in the available window space
				// The space for our list items is
				//	Total Page Height
				//  minus Height of the bluebar
				//  minus (Height of each menu section title) times (number of menu sections)
				//  minus some extra padding
				
				var blueBarHeight = 30; // From index.css
				var menuSectionTitleHeight = 25; // From index.css
				var numberOfMenuSections = arrMenuHeadings.length;
				var extraPadding = 10;
				
				var listItemDivHeight = totalPageHeight - blueBarHeight - (menuSectionTitleHeight * numberOfMenuSections) - extraPadding;
				
				// Determine the number of list items that can be displayed in the available space
				var heightOfEachListItem = 47; // from index.css
				var paddingBetweenEachListItem = 10; // from index.css
				var numberOfListItemsToBeDisplayed = Math.floor(listItemDivHeight / (heightOfEachListItem + paddingBetweenEachListItem));
				
				if (numberOfListItemsToBeDisplayed > responseObject.modules.length)
				{
					loopLimit = responseObject.modules.length;
					// No scroll handles need to be displayed
				}
				else
				{
					loopLimit = numberOfListItemsToBeDisplayed;
					// Display the scroll handles.			
					
					// Create the scroll icons
					scrollUpTop = blueBarHeight + (menuSectionTitleHeight*(currentMenuIndex+1)) + 5;
					scrollUpDiv = document.createElement('div');
					scrollUpDiv.setAttribute("style", "top:5px;height:21px; width:21px;background: url(adminPath+'/images/icons/arrowup.gif') no-repeat;");
					scrollUpDiv.style.cssText = "top:5px;height:21px; width:21px;background: url(adminPath+'/images/icons/arrowup.gif') no-repeat"; // For buggy IE
					scrollUpDiv.setAttribute('id', 'scrollUpDiv');
					scrollUpDiv.setAttribute('onmouseover', 'PerformScroll(-7)');
					
					scrollDownDiv = document.createElement('div');
					scrollDownDiv.setAttribute("style", "bottom:5px;height:21px; width:21px;background: url(adminPath+'/images/icons/arrowdown.gif') no-repeat;");
					scrollDownDiv.style.cssText = "bottom:5px;height:21px; width:21px;background: url(adminPath+'/images/icons/arrowdown.gif') no-repeat" ;
					scrollDownDiv.setAttribute('id', 'scrollDownDiv');
					scrollDownDiv.onmouseover = new Function("PerformScroll(7)");
					
					scrollContainer = document.getElementById('ScrollContainer');
					scrollContainer.appendChild(scrollUpDiv);
					scrollContainer.appendChild(scrollDownDiv);
				}
				for (var i=0; i<responseObject.modules.length; i++)
				{
					var moduleId = responseObject.modules[i].id;
					var moduleName = responseObject.modules[i].name;
					var moduleDisplayName = responseObject.modules[i].display_name;
					var moduleIcon = responseObject.modules[i].icon;
					
					var newListItem = document.createElement("li");
					newListItem.setAttribute('style', 'cursor: pointer');
					newListItem.style.cssText = 'cursor: pointer';
					newListItem.onclick = new Function("loadModule(" + moduleId + ", "+adminPath+")");
					
					if (moduleIcon == "")
					{
						newListItem.setAttribute("style", "background: url(adminPath+'/images/icons/noimage.jpg') no-repeat 50% 0;");
						newListItem.style.cssText = "background: url(adminPath+'/images/icons/noimage.jpg') no-repeat 50% 0;";
					}
					else
					{
						newListItem.setAttribute("style", "background: url(adminPath+'/images/icons/"+moduleIcon+"') no-repeat 50% 0;");
						newListItem.style.cssText = "background: url(adminPath+'/images/icons/"+moduleIcon+"') no-repeat 50% 0;";
					}
					
					newListItemSpan = document.createElement('span');
					betterInnerHtml(newListItemSpan, moduleDisplayName, true);
					
					newListItem.appendChild(newListItemSpan);
					outputLocation.appendChild(newListItem);
				}
				InitialiseScrollableArea();
				
				document.getElementById('leftbar').style.visibility = 'visible';
			}
		}
		else
		{
			if (xmlHttp.status == 404)
			{
				alert("Request failed because the target script does not exist.");
			}
			else if (xmlHttp.status == 503)
			{
				alert("Request failed because the target script permissions make it inaccessible.");
			}
			else
			{
				alert("Request failed with HTTP Response Code "+xmlHttp.status);
			}
		}
	}
}

function loadModule(moduleId, adminPath)
{
	// Request page with the appropriate moduleID
	var url = adminPath+'/?module='+moduleId;
	

	location.href ='/newadmin/?module='+moduleId;
}

function objMenuHeadings(iID, iName, iDisplayName)
{
	this.id = iID;
	this.name=iName;
	this.displayName = iDisplayName;
}

function showError(errorMessage, messageType, adminPath)
{
	// Get the error div and corner spans as objects
	errorDiv = document.getElementById('errorPane');
	errorDivText = document.getElementById('errorPaneText');
	rightCorner = document.getElementById('errorRightCorner');
	leftCorner = document.getElementById('errorLeftCorner');
	
	if (messageType == 'info' || messageType == 'information')
	{
		errorDiv.className = 'information';
		//errorDiv.setAttribute("style", "background-image: url('"+adminPath+"/images/errorPaneBottomInfo.jpg')");
		//rightCorner.setAttribute("style", "background-image: url('"+adminPath+"/images/errorPaneRightCornerInfo.jpg')");
		//leftCorner.setAttribute("style", "background-image: url('"+adminPath+"/images/errorPaneLeftCornerInfo.jpg')");
		
		// These are for IE, since it does not respect standards:
		//errorDiv.style.cssText = "background-image: url('"+adminPath+"/images/errorPaneBottomInfo.jpg')";
		//rightCorner.style.cssText = "background-image: url('"+adminPath+"/images/errorPaneRightCornerInfo.jpg')"
		//leftCorner.style.cssText = "background-image: url('"+adminPath+"/images/errorPaneLeftCornerInfo.jpg')"
	}
	else
	{
		//errorDiv.setAttribute("style", "background-image: url('"+adminPath+"/images/errorPaneBottom.jpg')");
		//rightCorner.setAttribute("style", "background-image: url('"+adminPath+"/images/errorPaneRightCorner.jpg')");
		//leftCorner.setAttribute("style", "background-image: url('"+adminPath+"/images/errorPaneLeftCorner.jpg')");
		
		// These are for IE, since it does not respect standards:
		//errorDiv.style.cssText = "background-image: url('"+adminPath+"/images/errorPaneBottom.jpg')";
		//rightCorner.style.cssText = "background-image: url('"+adminPath+"/images/errorPaneRightCorner.jpg')"
		//leftCorner.style.cssText = "background-image: url('"+adminPath+"/images/errorPaneLeftCorner.jpg')"
	}
	
	betterInnerHtml(errorDivText, errorMessage, true);
	errorDiv.style.display = 'block';
	
}
function rewriteExternalLinks()
{
	if (!document.getElementsByTagName) return; // If this browser doesn't support the method, all links will open in the same window
	
	var anchors = document.getElementsByTagName("a");
	
	for (var i=0; i < anchors.length; i++)
	{
		var anchor = anchors[i];
		
		if (anchor.getAttribute("href") && anchor.getAttribute("rel") == "external")
		{
			anchor.target = "_blank";
		}
	}
}

// Begin Scroll Functions
function verifyCompatibleBrowser()
{
	this.ver=navigator.appVersion;
	this.dom=document.getElementById?1:0;
	this.ie5=(this.ver.indexOf("MSIE 5")>-1 && this.dom)?1:0;
	this.ie4=(document.all && !this.dom)?1:0;
	this.ns5=(this.dom && parseInt(this.ver) >= 5) ?1:0;
	this.ns4=(document.layers && !this.dom)?1:0;
	this.bw=(this.ie5 || this.ie4 || this.ns4 || this.ns5);
	return this;
}

bw=new verifyCompatibleBrowser();

var speed=50;
var loop, timer;

function ConstructObject(obj,nest)
{
    nest=(!nest) ? '':'document.'+nest+'.';
    this.el=bw.dom?document.getElementById(obj):bw.ie4?document.all[obj]:bw.ns4?eval(nest+'document.'+obj):0;
    this.css=bw.dom?document.getElementById(obj).style:bw.ie4?document.all[obj].style:bw.ns4?eval(nest+'document.'+obj):0;
    this.scrollHeight=bw.ns4?this.css.document.height:this.el.offsetHeight;
    this.clipHeight=bw.ns4?this.css.clip.height:this.el.offsetHeight;
    this.up=MoveAreaUp;this.down=MoveAreaDown;
    this.MoveArea=MoveArea; this.x; this.y;
    this.obj = obj + "Object";
    eval(this.obj + "=this");
    return this;
}
function MoveArea(x,y)
{
    this.x=x;this.y=y;
    this.css.left=this.x;
	document.getElementById('activeSubMenu').style.top=this.y+"px";
}

function MoveAreaDown(move)
{
	if(this.y>-this.scrollHeight+objContainer.clipHeight)
	{
		this.MoveArea(0,this.y-move);
		if(loop)
		{
			setTimeout(this.obj+".down("+move+")",speed);
		}
	}
}
function MoveAreaUp(move)
{
	if(this.y<0)
	{
		this.MoveArea(0,this.y-move);
		if(loop)
		{
			setTimeout(this.obj+".up("+move+")",speed);
		}
	}
}

function PerformScroll(speed)
{
	if(initialised)
	{
		loop=true;
		if(speed>0)
		{
			objScroller.down(speed);
		}
		else
		{
			objScroller.up(speed);
		}
	}
}

function CeaseScroll()
{
	loop=false;
	if(timer)
	{
		clearTimeout(timer);
	}
}

var initialised;
function InitialiseScrollableArea()
{
    objContainer=new ConstructObject('ScrollContainer');
    objScroller=new ConstructObject('activeSubMenu','ScrollContainer');
    objScroller.MoveArea(0,0);
    initialised=true;
}
// End Scroll Functions
function getWindowWidth()
{
	var windowWidth = 780;
	if (parseInt(navigator.appVersion)>3)
	{
		if (navigator.appName=="Netscape")
		{
			windowWidth = window.innerWidth;
		}
		if (navigator.appName.indexOf("Microsoft")!=-1)
		{
			windowWidth = document.body.offsetWidth;
		}
		else
		{
			if (document.documentElement.clientWidth > 1)
			{
				windowWidth = document.documentElement.clientWidth;
			}
		}
	}
	return windowWidth;
}
function setModalPosition(iModalWidth, iDivName)
{
	var wWidth = getWindowWidth();
	
	if (iModalWidth)
	{
		var useModalWidth = iModalWidth;
	}
	else if (modalWidth)
	{
		var useModalWidth = modalWidth;
	}
	else
	{
		var useModalWidth = 500;
	}
	
	var leftEdge = wWidth/2 - useModalWidth/2;
	
	if (iDivName)
	{
		var targetElement = document.getElementById(iDivName);
	}
	else
	{
		var targetElement = document.getElementById('edit_div');
		targetElement.style.width = useModalWidth + "px";
	}
	targetElement.style.left = leftEdge + 'px';
}
function showAJAXWaitControl(outputMessage)
{
	if (outputMessage != '')
	{
		document.getElementById('scriptBusyOutput').innerHTML = outputMessage;
	}
	else
	{
		document.getElementById('scriptBusyOutput').innerHTML = "Working...";
	}
	document.getElementById('scriptBusyContainer').style.display = "block";
}
function hideAJAXWaitControl()
{
	document.getElementById('scriptBusyContainer').style.display = 'none';
}
// The following function controls the legend show/hide animation
$(document).ready(function()
{
	$("#legend_wrap").click(function()
	{
		if (jQuery.css(this, "height") == "0")
		{
			$("#legendControlActionId").html('<img src="images/legend_up.gif" />');
		}
		else
		{
			$("#legendControlActionId").html('<img src="images/legend_down.gif" />');
		}
		$("#legendContent").slideToggle("slow");
	});
});
// Modal-specific functions

// The response-handler used by almost all of the modules.
function modalResponseReceived(startYear,endYear,showSectionId)
{
	if (xmlHttpModal.readyState == 4 || xmlHttpModal.readyState == "complete")
	{
		if (xmlHttpModal.status==200)
		{
			// Determine if the is a JSON response containing an error object,
			// or if it is a regular, successful response			
			try
			{
				var responseObject = eval('('+xmlHttpModal.responseText+')');
				if (typeof(responseObject.error) == 'object')
				{
					handle_error(responseObject.error);
				}
				else
				{
					alert("ERROR:\n\nResponse is an object, but does not contain a valid error message. Please contact an administrator.");
				}
			}
			catch (err)
			{
				var tempnewdiv = document.createElement('div');
				tempnewdiv.innerHTML = xmlHttpModal.responseText;
				var container = document.getElementById('edit_div');
				
				// Clear anything that is already in 'edit_div'
				betterInnerHtml(container, '', true);
				container.appendChild(tempnewdiv);
		
				document.getElementById('modalContainer').style.display = "block";
				
				// If this is a multi-section edit div, show the requested section
				if (showSectionId != null)
				{
					showSection(showSectionId);
				}
				
				if (null == startYear || startYear < 1900)
				{
					var theDate = new Date();
					var startYear = theDate.getFullYear();
					
					if (null == endYear || endYear < 1900 || endYear > 2100)
					{
						var endYear = 2+theDate.getFullYear();
					}
				}
				
				$('.dhdatepicker').datepicker(
				{
					dateFormat: 'yy-m-d',
					buttonImage: 'images/calendar.png',
					buttonImageOnly: true,
					showOn: 'button',
					yearRange: startYear+':'+endYear		
				});
				
				return true;
			}
		}
		else if (xmlHttpModal.status==404)
		{
			alert("The request script is missing. Please contact an administrator.");
			return false;
		}
		else
		{
			alert("The request failed with HTTP response code " + xmlHttpModal.status + ". Please contact an administrator.");
			return false;
		}
	}
}
function showDiv(url, startYear, endYear, showSectionId)
{
	setModalPosition();
	
	xmlHttpModal = getXmlHttpObject();
	
	var date = new Date();
	var timestamp = date.getTime();
	
	// To avoid caching of the backend script
	url = url + '&time=' + timestamp;
	
	xmlHttpModal.onreadystatechange=function()
	{
		modalResponseReceived(startYear, endYear, showSectionId);
	}
	xmlHttpModal.open("GET",url,true);
	xmlHttpModal.send(null);
}
function hideEditDiv()
{
	document.getElementById('modalContainer').style.display = "none";
}
function showSection(sectionName)
{
	hideAllSections();

	document.getElementById('section_' + sectionName + '_li').className = 'section_active';
	thisSection = document.getElementById('section_'+sectionName);
	thisSection.className = 'showIt';

	/**
	// This has been commented for now, because the first-selected section may not always be
	// the tallest section. This causes a tall section to run out of the bottom of a too-small div.
		
	// Resize the height of the 'section_container' to equal the height of the
	// selected section. This height will remain constant when any subsequent 
	// section is selected.
	if (undefined === window.sectionHeight)
	{
		sectionHeight = document.getElementById('section_container').offsetHeight;
	}
	document.getElementById('section_container').setAttribute('style', 'height: '+sectionHeight+'px');
	*/
	try
	{
		document.getElementById('edit_task').value = saveType;
	}
	catch (e)
	{
		// Do nothing if 'saveType' is not set
	}
	
	/*
	// This set of focus() commands is specific to the Companies module,
	// and cannot be applied to all modules that use this function.
	// We have commented it out until we can find a way to apply it to all 
	// modules.
	
	switch(sectionName)
	{
		case 'general':
			document.getElementById('edit_company').focus();
			break;
		case 'url':
			document.getElementById('edit_url').focus();
			break;
		case 'coupons':
			document.getElementById('edit_alert').focus();
			break;
		case 'shipping':
			document.getElementById('edit_usship').focus();
			break;
	}
	*/
	
	return true;
}
function hideAllSections()
{
	var container = document.getElementById('section_container');
	for (var i=0; i<container.childNodes.length; i++)
	{
		try
		{
			var elementId = container.childNodes[i].id;
			if (elementId.substr(0, 8) == 'section_')
			{
				document.getElementById(elementId).className = 'hideIt';
				document.getElementById(elementId + '_li').className = 'section_inactive';
			}
		}
		catch (e)
		{
			// Do nothing if the element does not have an id
		}
	}
	return true;
}
function showBannerSelection(adminPath, selectedBannerId)
{
	setModalPosition(600, 'banner_selection_div');
	
	xmlBannersHttpModal = getXmlHttpObject();
	
	var date = new Date();
	var timestamp = date.getTime();
	
	var url = adminPath + '/modules/banners/modalContentsBannerSelection.php?id=' + selectedBannerId;
	url = url + '&time=' + timestamp;
	
	xmlBannersHttpModal.onreadystatechange=function()
	{
		bannerSelectionModalResponseReceived();
	}
	xmlBannersHttpModal.open("GET",url,true);
	xmlBannersHttpModal.send(null);
	
	return false;
}
function hideBannerSelection()
{
	document.getElementById('modalContainer_banners').style.display = "none";
	return false;
}
function bannerSelectionModalResponseReceived()
{
	if (xmlBannersHttpModal.readyState == 4 || xmlBannersHttpModal.readyState == 'complete')
	{
		if (xmlBannersHttpModal.status == 200)
		{
			// Clear anything that currently exists in the output div
			document.getElementById('banner_selection_div').innerHTML = xmlBannersHttpModal.responseText;
			
			document.getElementById('modalContainer_banners').style.display = "block";
		}
		else if (xmlBannersHttpModal.status == 404)
		{
			alert("The request script is missing. Please contact an administrator.");
		}
		else
		{
			alert("The request failed with HTTP response code " + xmlBannersHttpModal.status + ". Please contact an administrator.");
		}
	}
}
function registerBannerSelection(bannerId, bannerName)
{
	document.getElementById('modalContainer_banners').style.display = "none";
	
	document.getElementById('banner_name_span').innerHTML = bannerName;
	
	document.getElementById('banner_id').value = bannerId;
}
// End modal-specific functions

// Configuration form functions
function configurationForm_hideError()
{
	errorPaneId = 'errorPane';
	
	if (document.getElementById) // DOM3 = IE5, NS6
	{
		document.getElementById(errorPaneId).style.display = 'none';
	}
	else
	{
		if (document.layers) // Netscape 4
		{
			document.errorPaneId.display = 'none';
		}
		else // IE4
		{
			document.all.errorPaneId.style.display = 'none';
		}
	}
}

function configurationForm_showHide(elementTag, parentElement)
{
	elementDivId = 'configdiv_'+elementTag;
	
	// Show or hide the element
	
	if (document.getElementById) // DOM3 = IE5, NS6
	{
		if (document.getElementById(elementDivId).style.display == 'none')
		{
			document.getElementById(elementDivId).style.display = 'block';
		}
		else
		{
			document.getElementById(elementDivId).style.display = 'none';
		}
	}
	else
	{
		if (document.layers) // Netscape 4
		{
			if (document.elementDivId.display == 'none')
			{
				document.elementDivId.display = 'block';
			}
			else
			{
				document.elementDivId.display = 'none';
			}
		}
		else // IE4
		{
			if (document.all.elementDivId.style.display == 'none')
			{
				document.all.elementDivId.style.display = 'block';
			}
			else
			{
				document.all.elementDivId.style.display = 'none';
			}
		}
	}
}
// End configuration form functions

function getUrlParameter(name)
{
	// Return the value of the specified URL parameter, if it exists.
	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
	var regexS = "[\\?&]"+name+"=([^&#]*)";
	var regex = new RegExp( regexS );
	var results = regex.exec( window.location.href );
	if( results == null )
	{
    	return "";
    }
	else
    {
		return results[1];
	}
}