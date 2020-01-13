class ExtendedFileDriverHandler {
    /**
     * @type {string}
     */
    classMarker = 'efd-ajax-image';

    /**
     * @type {string}
     */
    classMarkerLoaded = 'efd-ajax-loaded';

    /**
     * @type {string}
     */
    imageRoute = 'contao/extendedfolder/image';

    /**
     * @type {HTMLCollection}
     */
    elements = null;

    /**
     *
     */
    constructor() {
    }

    /**
     * @param element
     * @returns {{mode: null, src: null, width: null, zoom: null, height: null}}
     */
    getDataSetFrom(element) {
        let dataSet = {
            'src': null,
            'width': null,
            'height': null,
            'mode': null,
            'zoom': null,
        };

        for (let prop in dataSet) {
            if (dataSet.hasOwnProperty(prop)) {
                dataSet[prop] = this.getDataFrom(element, prop);
            }
        }

        return dataSet;
    }

    /**
     * @param {Element} element
     * @param {String} field
     * @returns {null|String}
     */
    getDataFrom(element, field) {
        let value = null;
        switch (field) {
            case 'src':
                value = element.dataset.efdSrc;
                break;

            case 'width':
                value = element.dataset.efdWidth;
                break;

            case 'height':
                value = element.dataset.efdHeight;
                break;

            case 'mode':
                value = element.dataset.efdMode;
                break;

            case 'zoom':
                value = element.dataset.efdZoom;
                break;

            default:
                value = null;
                break;
        }

        if (typeof value === "undefined") {
            return null;
        }

        return value;
    }

    run() {
        this.searchMarkers();
        this.loadImages();
    }

    /**
     * @returns {ExtendedFileDriverHandler}
     */
    addTrigger() {
        let self = this;
        $(window).addEvent('ajax_change', function () {
            self.run();
        });

        return this;
    }

    /**
     * @returns {ExtendedFileDriverHandler}
     */
    searchMarkers() {
        this.elements = document.getElementsByClassName(this.classMarker);

        return this;
    }

    /**
     * @returns {ExtendedFileDriverHandler}
     */
    loadImages() {
        for (let i = 0; i < this.elements.length; i++) {
            let element = this.elements[i];
            if (element.classList.contains(this.classMarkerLoaded)) {
                continue;
            }

            element.classList.add(this.classMarkerLoaded);
            let dataset = this.getDataSetFrom(element);
            let parameters = '';
            for (let prop in dataset) {
                if (dataset.hasOwnProperty(prop)) {
                    if ('' === parameters) {
                        parameters = prop + '=' + dataset[prop];
                    } else {
                        parameters += '&' + prop + '=' + dataset[prop];
                    }
                }
            }

            this.sendRequest(element, parameters)
        }

        return this;
    }

    /**
     * @param {Element} element
     * @param {string} parameters
     */
    sendRequest(element, parameters) {
        let self = this;
        let xhttp = new XMLHttpRequest();

        xhttp.onload = function (e) {
            if (xhttp.readyState === 4) {
                if (xhttp.status === 200) {
                    let response = JSON.decode(xhttp.responseText);
                    if (response.state === 'ok') {
                        element.style.display = "none";
                        element.setAttribute('src', 'data:image/jpeg;base64,' + response.load);
                        element.setAttribute('width', element.dataset.efdWidth);
                        element.setAttribute('height', element.dataset.efdHeight);
                        element.style.display = "block";
                    } else {
                        self.setErrorElement(element, response.message);
                    }
                } else {
                    console.error(xhttp.statusText);
                    self.setErrorElement(element, 'Request error. Http status: ' + xhttp.status);
                }
            }
        };

        xhttp.onerror = function (e) {
            console.error(xhttp.statusText);
            self.setErrorElement(element, 'Request error. Http status: ' + xhttp.status);
        };

        xhttp.open("GET", this.imageRoute + '?' + parameters, true);
        xhttp.send(null);
    }

    /**
     * @param {Element} element
     * @param {string} error
     */
    setErrorElement(element, error) {
        let text = document.createElement('p');
        text.classList.add('preview-image');
        text.classList.add('broken-image');
        text.setAttribute('title', error);
        text.innerText = 'Broken image!';
        element.outerHTML = text.outerHTML;
    }
}

(function () {
    (window.ExtendedFileDriverHandler) ? '' : window.ExtendedFileDriverHandler = new ExtendedFileDriverHandler();
    window.ExtendedFileDriverHandler.addTrigger().run();
})();
