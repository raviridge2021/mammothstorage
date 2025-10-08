<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Unit Information</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>

<body>

    <div class="container mt-5">
        <!-- Button to Redirect to Profile Page -->
        <a href="<?php echo site_url('profile'); ?>" class="btn btn-primary">
            Go to Profile
        </a>
    </div>

    <div class="container mt-5">
        <!-- Section #1: Location Dropdown and Search Button -->
        <div class="text-center mb-4">
            <h1>Find a Centre</h1>
            <div class="form-group">
                <label for="location-select" class="font-weight-bold">Select Location</label>
                <select id="location-select" class="form-control w-50 mx-auto">
                    <option value="L001">Location L001</option>
                    <option value="L002">Location L002</option>
                    <option value="L003">Location L003</option>
                    <option value="L004">Location L004</option>
                    <option value="L005">Location L005</option>
                    <option value="L006">Location L006</option>
                </select>
            </div>
            <button id="get-units-btn" class="btn btn-primary">Search</button>
        </div>

        <!-- Section #2: Centre Information -->
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title">Hindmarsh</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Cnr Adam & Holden Streets, Hindmarsh, SA 5007</p>
                    <p><i class="fas fa-road"></i> 5.4km from your location</p>
                    <p><i class="fas fa-phone-alt"></i> <a href="tel:0874273234">08 7427 3234</a></p>
                    <a href="https://nationalstorage.com.au/locations/hindmarsh/" class="btn btn-link p-0">View Centre</a>
                </div>
                <div>
                    <a href="https://nationalstorage.com.au/locations/hindmarsh/" class="btn btn-dark">View Centre</a>
                </div>
            </div>
        </div>

        <!-- Section #3: Units Cards -->
        <div id="unit-list" class="row"></div>

        <!-- View More Button -->
        <button id="view-more-btn" class="btn btn-secondary mt-3 d-none" data-toggle="modal" data-target="#unitsModal">Show more units at this centre</button>
    </div>

    <!-- Modal for showing all units -->
    <div class="modal fade" id="unitsModal" tabindex="-1" aria-labelledby="unitsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unitsModalLabel">All Units at this Centre</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- List of all units will be displayed here -->
                    <div id="modal-unit-list" class="d-flex flex-wrap"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loader -->
    <div id="loader" class="text-center mt-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- Ensure jQuery, Bootstrap JS, and Font Awesome are loaded -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            let allUnits = []; // Store all units fetched from the API
            const unitSizeMap = {}; // To group units by exact size (e.g., 3m × 3m)
            const mainPageUnits = {
                Small: null,
                Medium: null,
                Large: null
            }; // Array to store 3 units for main page (Small, Medium, Large)
            const modalUnits = []; // Array to store all unique size units for modal display

            // Handle the Get Units button click event
            $('#get-units-btn').on('click', function() {
                fetchUnits(); // Call the function when button is clicked
            });

            // Fetch units from the server based on the selected location
            function fetchUnits() {
                const locationCode = $('#location-select').val();

                // Show loader while fetching data
                $('#loader').show();

                $.ajax({
                    url: 'units/fetch', // Clean route to fetch units
                    type: 'POST',
                    contentType: 'application/json', // Specify JSON content type
                    data: JSON.stringify({
                        locationCode: locationCode
                    }), // Send JSON payload
                    success: function(response) {
                        $('#loader').hide(); // Hide loader once the response is received
                        const parsedResponse = JSON.parse(response);

                        if (parsedResponse.error) {
                            alert('Error: ' + parsedResponse.message);
                        } else {
                            allUnits = parsedResponse.data; // Parse the response JSON

                            // Filter out units with length or width of 0
                            allUnits = allUnits.filter(unit => unit.dcLength > 0 && unit.dcWidth > 0);

                            // Group units by exact size (e.g., 3m × 3m)
                            groupUnitsBySize(allUnits);

                            // Display only 3 units (Small, Medium, Large) on the main page
                            displayCategoryUnits();

                            // Prepare modal array to display all unique size units
                            prepareModalUnits();
                        }
                    },
                    error: function(error) {
                        $('#loader').hide();
                        console.error('Error fetching units:', error);
                        alert('An error occurred while fetching units.');
                    }
                });
            }

            // Group units by exact size (e.g., 3m × 3m) and store them in unitSizeMap
            function groupUnitsBySize(units) {
                // Clear previous size groups
                Object.keys(unitSizeMap).forEach(key => delete unitSizeMap[key]);

                // Group units by their exact size and store UnitIDs
                units.forEach(unit => {
                    const sizeKey = `${unit.dcWidth}m × ${unit.dcLength}m`;
                    const category = getUnitCategory(unit.dcLength); // Get the category

                    if (!unitSizeMap[sizeKey]) {
                        unitSizeMap[sizeKey] = {
                            units: [], // Array to hold units of the same size
                            unitIds: [] // Array to hold UnitIDs for that size
                        };
                    }
                    unitSizeMap[sizeKey].units.push(unit); // Add unit details
                    unitSizeMap[sizeKey].unitIds.push(unit.UnitID); // Store UnitID

                    // Track one unit for each category (Small, Medium, Large) for the main page
                    if (!mainPageUnits[category]) {
                        mainPageUnits[category] = sizeKey; // Store the first unit of this category
                    }

                    // Push all unique sizes to the modal array (if not already added)
                    if (!modalUnits.includes(sizeKey)) {
                        modalUnits.push(sizeKey);
                    }
                });

                // Sort the modalUnits array based on size (by length first, then width)
                modalUnits.sort((a, b) => {
                    const [aWidth, aLength] = a.split('m × ').map(Number);
                    const [bWidth, bLength] = b.split('m × ').map(Number);
                    return aLength - bLength || aWidth - bWidth;
                });


            }

            // Define the unit categories (Small, Medium, Large) based on length
            function getUnitCategory(length) {
                if (length < 2) return 'Small';
                if (length >= 2 && length < 3) return 'Medium';
                if (length >= 3) return 'Large';
            }

            // Display one unit from each category (Small, Medium, Large) on the main page
            function displayCategoryUnits() {
                const unitListDiv = $('#unit-list');
                unitListDiv.html(''); // Clear current display

                // Only show one unit per category (Small, Medium, Large)
                for (const category in mainPageUnits) {
                    const sizeKey = mainPageUnits[category];
                    if (sizeKey && unitSizeMap[sizeKey]) {
                        const unit = unitSizeMap[sizeKey].units[0]; // Get the first unit for this size
                        const unitHtml = `
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">${sizeKey} - (${unit.dcWidth * unit.dcLength}m²) <span class="badge badge-info">${category}</span></h5>
                                    <p><strong>$${unit.dcStdRate.toFixed(2)}/mo</strong></p>
                                    <a href="#" class="btn btn-primary" onclick="selectRandomUnitFromSize('${sizeKey}')">Continue</a>
                                </div>
                            </div>
                        </div>
                    `;
                        unitListDiv.append(unitHtml);
                    }
                }

                // Show the 'View More' button if there are more than 3 unique unit sizes
                if (modalUnits.length > 3) {
                    $('#view-more-btn').removeClass('d-none');
                }
            }

            // Prepare the modal array with all unique size units sorted by size
            function prepareModalUnits() {
                const modalUnitList = $('#modal-unit-list');
                modalUnitList.html(''); // Clear the modal list

                // Loop through each exact size in the modal array
                modalUnits.forEach(sizeKey => {
                    const unit = unitSizeMap[sizeKey].units[0]; // Get the first unit for this size
                    const unitHtml = `
                    <div class="col-12 mb-3">
                        <div class="card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title">${sizeKey} - (${unit.dcWidth * unit.dcLength}m²)</h5>
                                    <p><strong>$${unit.dcStdRate.toFixed(2)}/mo</strong></p>
                                </div>
                                <a href="#" class="btn btn-primary" onclick="selectRandomUnitFromSize('${sizeKey}')">Continue</a>
                            </div>
                        </div>
                    </div>
                `;
                    modalUnitList.append(unitHtml);
                });
            }

            // Show the modal with all unique sizes when "View More" is clicked
            $('#view-more-btn').on('click', function() {
                $('#unitsModal').modal('show'); // Show the modal
            });

            // Randomly select a UnitID from the specific size group but don't display it in the UI
            window.selectRandomUnitFromSize = function(sizeKey) {
                const sizeGroup = unitSizeMap[sizeKey];
                const randomIndex = Math.floor(Math.random() * sizeGroup.unitIds.length);
                const randomUnitId = sizeGroup.unitIds[randomIndex];
                // Perform any action here with the randomly selected UnitID (e.g., redirect or proceed)
                alert(`Selected Unit ID: ${randomUnitId}`);
            };
        });
    </script>


</body>

</html>