// When the window has finished loading create our google map below

            google.maps.event.addDomListener(window, 'load', init);

        

            function init() {

                // Basic options for a simple Google Map

                // For more options see: https://developers.google.com/maps/documentation/javascript/reference#MapOptions

                var mapOptions = {

                    // How zoomed in you want the map to start at (always required)

                    zoom: 14,

                    scrollwheel: false,

                    navigationControl: false,

                    mapTypeControl: false,

                    scaleControl: false,

                    draggable: false,
                    scrollwheel: true,
                    disableDoubleClickZoom: true,
                    zoomControl: true,



                    // The latitude and longitude to center the map (always required)

                    center: new google.maps.LatLng(53.8447401, 27.4750792), // New York



                    // How you would like to style the map. 

                    // This is where you would paste any style found on Snazzy Maps.

                    styles: [{"featureType":"administrative.land_parcel","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"landscape.man_made","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"poi","elementType":"labels","stylers":[{"visibility":"off"}]},{"featureType":"road","elementType":"labels","stylers":[{"visibility":"simplified"},{"lightness":20}]},{"featureType":"road.highway","elementType":"geometry","stylers":[{"hue":"#f49935"}]},{"featureType":"road.highway","elementType":"labels","stylers":[{"visibility":"simplified"}]},{"featureType":"road.arterial","elementType":"geometry","stylers":[{"hue":"#fad959"}]},{"featureType":"road.arterial","elementType":"labels","stylers":[{"visibility":"off"}]},{"featureType":"road.local","elementType":"geometry","stylers":[{"visibility":"simplified"}]},{"featureType":"road.local","elementType":"labels","stylers":[{"visibility":"simplified"}]},{"featureType":"transit","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"all","stylers":[{"hue":"#a1cdfc"},{"saturation":30},{"lightness":49}]}]

                };



                // Get the HTML DOM element that will contain your map 

                // We are using a div with id="map" seen below in the <body>

                var mapElement = document.getElementById("map");

                var myLatlng = new google.maps.LatLng(53.8447401, 27.4750792);

                // Create the Google Map using out element and options defined above

                var map = new google.maps.Map(mapElement, mapOptions);



                var marker = new google.maps.Marker({

                    position: myLatlng,

                    title:"проспект Дзержинского 131",

                    icon:'img/map_marker.png'

                });



                // To add the marker to the map, call setMap();

                marker.setMap(map);

                }