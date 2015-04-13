(function ($) {

    //////////////////////////////////////////////////////////
    // API Proxy
    //////////////////////////////////////////////////////////

    // Constants

    var apiBaseUrl = '?action=onwp_ajax_api_proxy&resource=',
        sortBy = 'name',  // Field by which to sort the displayed results
        apiNotebooksUrl = '/notebooks',
        apiExpandUrl = '?$expand=' +
                       encodeURIComponent('sections,sectionGroups($expand=sections,sectionGroups($levels=max;$expand=sections))') +
                       '&$filter=' +
                       encodeURIComponent('userRole ne Microsoft.OneNote.Api.UserRole\'Reader\'') +
                       '&$orderby=' +
                       encodeURIComponent(sortBy + ' desc'),
        apiSectionsUrl = '/sections',
        apiPagesUrl = '/pages',
        apiPageContentUrl = '/content',
        contentTypeJson = 'application/json',
        contentTypeHtml = 'application/xhtml+xml',
        contentTypeDefault = 'application/x-www-form-urlencoded',
        requestTimeout = 30000,  // Timeout for requests
        sectionCompareCallback = function (section1, section2) {
            var name1 = section1[sortBy],
                name2 = section2[sortBy],
                returnValue = 0;

            if (name1 > name2) {
                returnValue = -1;
            }
            else if (name1 < name2) {
                returnValue = 1;
            }

            return returnValue;
        },

    // Globals

        notebookHierarchyCache,  // Saves the entire notebooks/section groups/sections hierarchy
        hierarchyObjectMap = {};  // After a hierarchy is returned for the list of notebooks (all noteboks & their sections/section groups), this map
    // map will be used to easily find objects in the hierarchy by their OneNote object ID.

    function callApi(apiUrl) {
        return $.ajax({
            url: ajaxUrl + apiBaseUrl + encodeURIComponent(apiUrl),
            type: 'GET',  // If httpVerb is not specified, default to GET
            timeout: requestTimeout,
        }).fail(
            function (xmlHttp) {
                if (xmlHttp.status == 401) {
                    location.href = '?re-authenticate';
                }
            }
        );
    }

    function mapSingleObject(singleObject) {
        hierarchyObjectMap[singleObject.id] = singleObject;
    }

    // Loop over the hierarchy and map objects according to their ID for easy retrieval later.
    // Each object in the hierarchy will be assigned a type so it could be identified later.
    function mapHierarchy(hierarchy, type) {
        if (hierarchy) {  // Make sure the collection exists
            for (var hierarchyIndex = 0; hierarchyIndex < hierarchy.length; hierarchyIndex++) {
                // Map the current object
                var currentObject = hierarchy[hierarchyIndex];
                currentObject._type = type;
                mapSingleObject(currentObject);

                // If the current object has a sections or section groups collections, traverse those too
                // The .sections & .sectionGroups might be empty.

                mapHierarchy(currentObject.sections, 'section');
                mapHierarchy(currentObject.sectionGroups, 'sectionGroup');
            }
        }
    }

    // Request the entire notebooks/section groups/sections hierarchy and returns the list of notebooks
    function getAllNotebooks(success, failure) {
        callApi(apiNotebooksUrl + apiExpandUrl).done(
            function (notebooksHierarchy) {
                notebookHierarchyCache = JSON.parse(notebooksHierarchy).value;
                notebookHierarchyCache.sort(sectionCompareCallback);
                mapHierarchy(notebookHierarchyCache, 'notebook');  // Map the hierarchy of objects for easy retrieval by ID without traversing the hierarchy
                success(notebookHierarchyCache);
            }
        ).fail(
            function () {
                failure();
            }
        );
    }

    // Returns sections & section groups as a single sorted collection for the parentObject
    function getSortedSectionsAndSectionGroups(parentObject) {
        var sections = [];

        sections.push.apply(sections, parentObject.sections);
        sections.push.apply(sections, parentObject.sectionGroups);
        sections.sort(sectionCompareCallback);  // Sort the sections
        return sections;
    }

    // Look for the requested notebook sections & section groups in the cached notebook hierarchy object
    function getAllSectionsForNotebook(notebookId, success, failure) {
        var foundNotebook = null;

        for (var notebookIndex = 0; notebookIndex < notebookHierarchyCache.length; notebookIndex++) {  // Loop on all the notebooks
            var notebook = notebookHierarchyCache[notebookIndex];
            if (notebook.id == notebookId) {  // Requested notebook found?
                foundNotebook = notebook;  // Found!
                break;
            }
        }

        if (foundNotebook) {  // Did we find the notebook?
            // Merge the sections and the section groups to one sorted array
            var sections = getSortedSectionsAndSectionGroups(foundNotebook);
            success(sections);
        }
        else {
            failure();
        }
    }

    function getAllPagesOrSubSectionsForSection(sectionId, success, failure) {
        var currentSection = hierarchyObjectMap[sectionId];  // Get the current section object

        // If the current section object is a regular section, request its pages
        if (currentSection._type == 'section') {
            callApi(apiSectionsUrl + '/' + sectionId + apiPagesUrl).done(
                function (pagesData) {
                    var parsedPages = JSON.parse(pagesData).value;  // Parse the returned pages list

                    // Set the object type
                    for (var pageIndex = 0; pageIndex < parsedPages.length; pageIndex++) {
                        parsedPages[pageIndex]._type = 'page';
                    }

                    success(parsedPages);  // Send back the pages list
                }
            ).fail(failure);  // Error...
        }
        else {  // The current section is a sections group so send back a list of sections and section groups
            var sectionsAndSectionGroups = getSortedSectionsAndSectionGroups(currentSection);  // Get the sorted list
            success(sectionsAndSectionGroups);  // Returns the list
        }
    }

    //////////////////////////////////////////////////////////
    // Object Containers
    //////////////////////////////////////////////////////////

    function OneNoteObjectContainer(
        container,  // JQuery object representing the visual container of the current object
        data,  // Data returned by OneNote API containing information about the cureent object
        isParent,  // Can the current object contain child items? (like section which can contain pages)
        icon,  // Path to icon representing the current object type
        nestingLevel  // The nesting level of the current object (always +1 from this object's parent)
    ) {
        this.container = container;
        this.oneNoteId = data ? data.id : 'root';
        this.id = getInternalId();  // Create an internal ID for the new object
        this.items = [];  // List of child items
        this.itemsLoaded = false;  // When false, the child items for this parent object have not been loaded from the server yet
        this.data = data;  // Data returned from the API about this object like id & name
        this.isParent = isParent;
        this.icon = icon;
        this.expanded = false;  // Expander status
        this.highlighted = false;  // True when the current object is highlighted = selected
        this.nestingLevel = nestingLevel || 0;  // This value is used to indent the current object so we could display a tree like formation, default value is zero to indicate the root element
        this.leftPadding = (baseNestPadding + (this.nestingLevel * nestPaddingStep));

        /*

        Classes inheriting from this base class must implement the following

        // Makes an API call to return the child items of the current object
        this.loadItemsApi()

        // Takes the item API data returned as a simple JSON and returns an object
        // class which represents that specific item type.
        this.convertItemApiDataToObject(itemApiData)

        */

        // Returns the object's name from the data returned by the API
        this.getName = function () {
            return this.data.name;
        }

        // This method loops through the images in the the specified container and finds/replaces
        // the specifiede strings in the images sources in order to change all images in the container
        // from one version to another for example, from regular images to their highlighted versions
        // and vice versa.
        this.changeImages = function (srcFind, srcReplaceTo) {
            this.header.find('img').each(  // Loop on all images
                function (index, element) {
                    var image = $(element),
                        src = image.attr('src'),  // Get the original src value
                        selectedSrc = src.replace(srcFind, srcReplaceTo);  // Get the updated src value

                    image.attr('src', selectedSrc);  // Change to the new image
                }
            );
        }

        this.draw = function () {
            var html = '<div id="' + this.id + '">' +  // Main element
                       '<div class="on-header" style="padding-left: ' + this.leftPadding + 'px;">';  // Header container (contains the expander, icon & the title)

            // Can the new object contain other objects?
            if (this.isParent) {
                html += '<span class="on-expander">' +  // Expander element
                        '<img src="' + imagesPath + '/collapsed-node.png"/>' +  // Expander image
                        '</span>';  // Close the expander element
            }
            else {  // When an object is not a parent object (does not contain child items), just create a spacer element to keep the spacing correct
                html += '<span class="on-spacer" style="width: ' + nestPaddingStep + 'px; display: inline-block;">' +  // Spacer element
                        '&nbsp;' +  // Just some content to make this element take the needed space
                        '</span>';  // Close the spacer element
            }

            if (icon) {  // Append an icon for the current object?
                html += '<img src="' + imagesPath + '/' + icon + '.png"/>';
            }

            var name = this.getName();
            if (name.length == 0) {
                name = 'Untitled';
            }

            html += name +  // The current object's name
                    '</div>';  // Close the header element

            if (this.isParent) {
                html += '<div class="on-child-items on-hidden">' +  // Add the child items container
                        '</div>';  // Close the child items container
            }

            html += '</div>';  // Close the object's main element
            this.element = $(html);  // Create the new object's element
            this.header = this.element.find('.on-header');
            this.expander = this.header.find('.on-expander');

            if (this.isParent) {
                this.itemsContainer = this.element.find('.on-child-items');
            }

            // Event handlers

            // Handle clicking on the header element
            this.header.click(
                (function (currentObject) {
                    return function () {
                        currentObject.highlight();
                    }
                })(this)
            );

            // Handle clicking on the expander element
            this.expander.click(
                (function (currentObject) {
                    return function () {
                        currentObject.toggleItems();
                    }
                })(this)
            );

            this.container.prepend(this.element);  // Append the new element to its container element
        }

        // Converts the JSON data objects which represent individual child items into
        // their respective object classes.
        this.parseItemsData = function (parsedItemsData) {
            this.items = [];  // Clear the items list
            this.itemsContainer.empty().hide();  // Clear the child items container & make sure it is hidden

            for (var itemsCounter = 0; itemsCounter < parsedItemsData.length; itemsCounter++) {
                var parsedItemData = parsedItemsData[itemsCounter]
                this.addItem(parsedItemData, true);
            }
        }

        // Append a child item to the current parent object.
        this.addItem = function (parsedItemData) {
            var itemObject = this.convertItemApiDataToObject(parsedItemData);
            this.items.push(itemObject);
            this.expander.css('visibility', 'visible');  // Make sure the expander element of the current object is visible now that it has child items
            return itemObject;
        }

        // Makes sure the current object is scrolled into view if it is currently scrolled off the visible screen
        this.scrollIntoView = function () {
            this.container[0].scrollIntoView();
        }

        // Calls the items loading API method and handles successful and failed results
        this.loadItems = function (successCallback) {
            if (!this.itemsLoaded) {  // Did we already load the items?
                loading('loading');  // Show the loading... indicator
                this.loadItemsApi(
                    (function (currentObject) {  // API call finished successfully
                        return function (apiItemsData) {
                            currentObject.itemsLoaded = true;
                            // If no items were returned, just hide the expander element
                            if (apiItemsData.length == 0) {
                                currentObject.expander.css('visibility', 'hidden');
                            }

                            // Parse and draw the returned elements
                            currentObject.parseItemsData(apiItemsData)
                            successCallback();
                            loading();  // Hide the loading... indicator
                            hideError();  // Clear the error notifications area if it was on before
                        }
                    })(this),
                    function () {
                        loading();  // Hide the loading... indicator
                        showError();  // Handle an error
                    }
                );
            }
            else {  // Items already loaded, just call the success callback
                successCallback();
            }
        }

        // Expand or collapse the container so it's child elements are visible
        // This methods loads the child items from the server.
        this.toggleItems = function () {
            if (this.isParent) {  // Make sure we have something to expand
                this.highlight();

                var expansionState = this.expanded,
                    expanderImage = this.expander.find('img').first(),
                    suffixForHighlightedMode = (this.highlighted ? selectedImagesSuffix : '');  // This will contain the highlighted image version suffix in case the current object is highlighted or an empty string otherwise

                if (expansionState) {  // Collapse the child items container
                    this.itemsContainer.slideUp(animationTimespan);
                    expanderImage.attr('src', imagesPath + '/collapsed-node' + suffixForHighlightedMode + '.png');  // Set the expander image
                }
                else {  // Load the items and show the child items container
                    this.loadItems(
                        (function (currentObject) {  // When the loading is done, show the child items container
                            return function () {
                                currentObject.itemsContainer.slideDown(animationTimespan);
                                expanderImage.attr('src', imagesPath + '/expanded-node' + suffixForHighlightedMode + '.png');  // Set the expander image
                            }
                        })(this)
                    );
                }

                this.expanded = !expansionState;
            }
        }

        // Highlighs the current element
        this.highlight = function () {
            // Make sure the current element is not already selected
            if (!this.highlighted) {
                // Handle highlighting the current object:
                // First figure out if there was an element previously selected and if so, clear its selection
                if (selectedElement != null) {
                    selectedElement.unHighlight();
                }

                // Selected & highlight the current element
                this.highlighted = true;
                this.header.addClass('on-header-selected');
                this.changeImages('.png', selectedImagesSuffix + '.png');  // Change all of the images to their highlighted versions
                setSelectedElement(this);
            }
        }

        this.unHighlight = function () {
            this.highlighted = false;
            this.header.removeClass('on-header-selected');
            this.changeImages(selectedImagesSuffix + '.png', '.png');  // Revert to the non-highlighted images
        }
    }

    function OneNotePage(container, data, nestingLevel) {
        // Copy the methods and members of the OneNoteObjectContainer object
        $.extend(
            this,
            new OneNoteObjectContainer(
                container,
                data,
                false,
                'page',
                nestingLevel
            )
        );

        // Override the default get name as the page API uses title attribute instead of name
        this.getName = function () {
            return this.data.title;
        }

        this.draw();
    }

    function OneNotePagesContainer(container, data, nestingLevel) {
        // Copy the methods and members of the OneNoteObjectContainer object
        $.extend(
            this,
            new OneNoteObjectContainer(
                container,
                data,
                true,
                data._type == 'section' ? 'section' : 'section-group',  // Use the right icon according to the object type (either section or section group)
                nestingLevel
            )
        );

        // Set the API method to call for loading the notebook items
        this.loadItemsApi = function (success, failure) {
            getAllPagesOrSubSectionsForSection(this.oneNoteId, success, failure);
        }

        // Takes the item API data returned as a simple JSON and returns an object
        // class which represents that specific item type.
        this.convertItemApiDataToObject = function (itemApiData) {
            var type = itemApiData._type;

            if (type == 'section' || type == 'sectionGroup') {
                return new OneNotePagesContainer(this.itemsContainer, itemApiData, this.nestingLevel + 1);
            }
            else {
                return new OneNotePage(this.itemsContainer, itemApiData, this.nestingLevel + 1);
            }
        }

        this.draw();
    }

    function OneNoteSectionsContainer(container, data, nestingLevel) {
        // Copy the methods and members of the OneNoteObjectContainer object
        $.extend(
            this,
            new OneNoteObjectContainer(
                container,
                data,
                true,
                'notebook',
                nestingLevel
            )
        );

        // Set the API method to call for loading the notebook items
        this.loadItemsApi = function (success, failure) {
            getAllSectionsForNotebook(this.oneNoteId, success, failure);
        }

        // Takes the item API data returned as a simple JSON and returns an object
        // class which represents that specific item type.
        this.convertItemApiDataToObject = function (itemApiData) {
            return new OneNotePagesContainer(this.itemsContainer, itemApiData, this.nestingLevel + 1);
        }

        this.draw();
    }

    function OneNoteNotebookContainer(container) {
        // Copy the methods and members of the OneNoteObjectContainer object
        $.extend(
            this,
            new OneNoteObjectContainer(
                container,
                { name: 'All Notebooks' },
                true
            )
        );

        // Set the API method to call for loading the notebook items
        this.loadItemsApi = getAllNotebooks;

        // Takes the item API data returned as a simple JSON and returns an object
        // class which represents that specific item type.
        this.convertItemApiDataToObject = function (itemApiData) {
            return new OneNoteSectionsContainer(this.itemsContainer, itemApiData, 1);
        }

        this.draw();
    }

    //////////////////////////////////////////////////////////
    // Controller
    //////////////////////////////////////////////////////////

    // Constants

    var imagesPath,
        ajaxUrl,
        selectedImagesSuffix = '-selected',
        errorMessage = 'We can not complete your request now. Please try again later',
        internalIdPrefix = '_',  // A prefix to make the numeric internal ID usable as an ID in the dom
        animationTimespan = 200,  // The time span during which animation effects should complete (like show / hide of elements)
        baseNestPadding = 20,  // Minumum value from which to start the indentation (the rott element will be indented by this many pixels and each nesting level will add this value to the nesting level indentation calculation).
        nestPaddingStep = 30,  // In order to calculate the nesting indentation value, we will multiple the nestingLevel by this value and add the baseNestPadding

    // Globals

        internalIdCounter = 0,  // Simple incremental counter for creating internal object IDs
        selectedElement = null,
        pickerContainer = null,  // Holds a reference to the main picker container element
        addingNewItems = false;  // Indicates if the user is currently in the process of adding a new items and the "add new item" section is displayed.  Used to prevent adding multiple "add" sections while one is visible

    // Returns a new internal object ID
    function getInternalId(oneNoteId) {
        var internalId = internalIdPrefix + internalIdCounter++;
        return internalId;
    }

    function showError() {
        $('.on-error-message').show().text(errorMessage);
        loading();  // Clear loading status messages
    }

    function hideError() {
        $('.on-error-message').hide().empty();
    }

    // Place the modal blocker on top of the picker UI so no event will be triggered while
    // a lengthy operation is taking place.
    function blockUI() {
        var modalBlock = pickerContainer.find('.on-modal-block'),  // Find the floating blocker element
            pickerOffset = pickerContainer.offset(),  // Get the (top, left) position of the picker element
            size = {  // Get the width & height of the picker element
                width: pickerContainer.outerWidth(),
                height: pickerContainer.outerHeight()
            };

        // Place the invisible element on top of all other elements.
        modalBlock.width(size.width).  // Set width
                    height(size.height).  // Set height
                    offset(pickerOffset).  // Set offset
                    show();  // "Show" the element (it's actually invisible but it's there intercepting all UI events)
    }

    // Release the blocked UI by hiding the blocking element
    function releaseUI() {
        var modalBlock = pickerContainer.find('.on-modal-block');
        modalBlock.hide();
    }

    // Show either: Loading, Saving or Done
    // If not type is specified, all of the elements in the status pane will be hidden
    // If html is specified, it is used to replace the content of the element which will be shown
    function loading(type, html) {
        blockUI();
        // Loop for each of the elements in the status page and if we find one that matches
        // the specified type, show it, otherwise hide it.
        pickerContainer.find('.on-status-pane').children().each(
            function (index, statusElement) {
                var statusObj = $(statusElement);

                if (statusObj.attr('class').indexOf(type) != -1) {
                    if (html) {
                        statusObj.html(html);
                    }

                    statusObj.show();
                }
                else {
                    statusObj.hide();
                }
            }
        );

        // If we need to cleasr all status messages or the message is "Done!", remove the UI blocker
        if (!type) {
            releaseUI();
        }
    }

    // Called when a new element is selected (Notebook, Section, Section Group or Page)
    function setSelectedElement(selectedElementRef) {
        selectedElement = selectedElementRef;

        // If the selected element is a Notebook or a Section Group, enable the creation of a new section under that element.
        // Also, make sure the user can only save if a section or a page are selected by switching the state of the Ok button.
        var type = selectedElement.data._type,
            okButton = pickerContainer.find('.on-ok-button');

        if (type == 'page') {
            okButton.removeAttr('disabled');
        }
        else {
            okButton.attr('disabled', 'true');
        }
    }

    function initialize(containerElementSelector, callback, globalImagesPath, globalAjaxUrl) {
        imagesPath = globalImagesPath;
        ajaxUrl = globalAjaxUrl;

        // Create dialog box content
        var rootElement = $(
            '<div class="on-picker">' +  // Root container
            '<div class="on-modal-block on-hidden"></div>' +  // Floating element used to block the UI during load/save operations
            '<div class="on-root-element">' +  // Container for the notebook/sections/pages tree
            '</div>' +  // End of notebook/sections/pages tree container
            '<div class="on-error-message on-hidden"></div>' +  // Error message container
            '<div class="on-dialog-buttons">' +  // Action buttons & status messages container
            '<span class="on-status-pane">' +  // Container for the various status messages such as loading/saving done
            '<span class="on-status-loading on-hidden">' +  // Loading message container
            '<img src="' + imagesPath + '/please-wait.gif" align="bottom"/> Loading...' +  // Loading message
            '</span>' +  // End of loading message
            '<span class="on-status-saving on-hidden">' +  // Saving message container
            '<img src="' + imagesPath + '/please-wait.gif" align="bottom"/> Saving...' +  // Saving message
            '</span>' +  // End of saving message
            '</span>' +  // End of status messages container
            '<button class="on-ok-button" disabled>OK</button>' +  // Ok button
            '</div>' +  // End of action buttons & status messages container
            '</div>'  // End of root container
        );

        // Append the root element HTML to the picker container
        pickerContainer = $(containerElementSelector);
        pickerContainer.append(rootElement);

        // Hook the events for the "ok" button
        rootElement.find('.on-ok-button').click(  // User pressed the Ok button
            function () {
                callback(selectedElement.data.id);
            }
        );

        // Create the notebooks container object
        var noteBooksContainer = rootElement.find('.on-root-element');
        var notebooks = new OneNoteNotebookContainer(noteBooksContainer);
        notebooks.toggleItems();
    }

    window._OneNotePickerLaunch = initialize;
})(jQuery);