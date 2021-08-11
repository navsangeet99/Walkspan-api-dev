var map_handle = null;

L.TileLayer.BetterWMS = L.TileLayer.WMS.extend({
	
    onAdd: function (map) {

        map_handle = map;

        // Triggered when the layer is added to a map.
        //   Register a click listener, then do all the upstream WMS things
        L.TileLayer.WMS.prototype.onAdd.call(this, map);
        map.on('click', this.getFeatureInfo, this);
    },
	
    onRemove: function (map) {

        map_handle = map;

        // Triggered when the layer is removed from a map.
        //   Unregister a click listener, then do all the upstream WMS things
        L.TileLayer.WMS.prototype.onRemove.call(this, map);
        map.off('click', this.getFeatureInfo, this);
    },
	
    getFeatureInfo: function (evt) {
        // Make an AJAX request to the server and hope for the best
        var url = this.getFeatureInfoUrl(evt.latlng); //,
        // showResults = L.Util.bind(this.showGetFeatureInfo, this);

			

		var deferred = $.ajax({
            url: url,
            type: 'GET',
            beforeSend: function (xhr) {
                // Empty
            },
            success: function (data, textStatus, xhr) {

                if ((! data.features) || (data.features.length == 0)) return;

                var siteName = data.features[0].properties.name;
                var groupName = data.features[0].properties.groupname;
                var popupHtml = '<div style="font-weight: bold;"> ' + siteName + '</div>' +
                        '<div> Group: ' + groupName + '</div>';

                var popup = L.popup()
                    .setLatLng(evt.latlng)
                    .setContent(popupHtml)
                    .openOn(map_handle);
            },
            complete: function (xhr, textStatus) {
                // Complete
            },
            error: function (xhr, textStatus, errorThrown) {
                // Error
            }
        });
    },
	
    getFeatureInfoUrl: function (latlng) {
        // Construct a GetFeatureInfo request URL given a point
        var point = this._map.latLngToContainerPoint(latlng, this._map.getZoom()),
            size = this._map.getSize(),
			
            params = {
                request: this.wmsParams.request,
                service: 'WMS',
                srs: 'EPSG:4326',
                styles: this.wmsParams.styles,
                transparent: this.wmsParams.transparent,
                version: this.wmsParams.version,
                format: this.wmsParams.format,
                bbox: this._map.getBounds().toBBoxString(),
                height: size.y,
                width: size.x,
                layers: this.wmsParams.layers,
                query_layers: this.wmsParams.layers,
				cql_filter: this.wmsParams.cql_filter,
                info_format: 'application/json'
            };
			
        params[params.version === '1.3.0' ? 'i' : 'x'] = point.x;
        params[params.version === '1.3.0' ? 'j' : 'y'] = point.y;

        return this._url + L.Util.getParamString(params, this._url, true);
    },
	
    showGetFeatureInfo: function (err, latlng, content) {
        if (err) { console.log(err); return; } // do nothing if there's an error

        // Otherwise show the content in a popup, or something.
        L.popup({ maxWidth: 800 })
            .setLatLng(latlng)
            .setContent(content)
            .openOn(this._map);
    }
});

L.tileLayer.betterWms = function (url, options) {
    return new L.TileLayer.BetterWMS(url, options);
};