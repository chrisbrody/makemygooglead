jQuery(document).ready(function($) {
    var body = $('body')
    // Add spinner HTML
    body.append(`
        <div id="global-spinner" class="spinner-overlay">
            <div class="spinner-content">
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    `);

    // spinner
    const showSpinner = () => {
        $('#global-spinner').show();
        body.css('overflow', 'hidden');
    }
    const hideSpinner =() => {
        $('#global-spinner').hide();
        body.css('overflow', '');
    }
    // error message
    const displayError = (message) => {
        $('#error-message').html('<div class="alert alert-danger" role="alert">' + message + '</div>').show();
    }

    // Reusable input validation function
    const validateInput = (inputElement, validationMessageElement) => {
        const input = $(inputElement);
        const validationMessageId = `${input.attr('id')}-validation-message`;

        // Check if validation message element exists, otherwise create it
        if ($(`#${validationMessageId}`).length === 0) {
            input.after(`<div id="${validationMessageId}" class="text-danger mt-2"></div>`);
        }
        const validationMessage = $(`#${validationMessageId}`);

        // Input event listener
        input.on('input blur', function() {
            validateField();
        });

        // Validation function
        const validateField = () => {
            const value = input.val().trim();
            validationMessage.text('');

            if (value === '') {
                validationMessage.text(`Please enter a ${input.attr('placeholder')}. This field cannot be empty.`);
                return false;
            }
            return true;
        };

        return validateField;
    };

    // Set up validation for industry input
    const validateIndustry = validateInput('#industry', '#industry-validation-message', '#error-message');
    $('#industry-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');

        if (!validateIndustry()) {
            return; // Prevent form submission if the validation fails
        }

        var industryForm = $('#industry-form');
        var industry = $('#industry').val();
        if(industry === '') {
            displayError('Please enter an industry. This field cannot be empty.');
            return;
        }
        industryForm.hide();
        showSpinner();

        $.ajax({
            url: howdoisellthis_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_subreddits',
                nonce: howdoisellthis_ajax.nonce,
                industry: industry
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    console.log('Subreddits received:', response.data);
                    displaySubreddits(response.data);
                } else {
                    console.error('Error fetching subreddits:', response.data.message);
                    displayError('Error fetching subreddits: ' + response.data.message);
                }

                hideSpinner();

            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                industryForm.show();
                displayError('Error connecting to the server: ' + textStatus);
                hideSpinner();
            }
        });
    });

    function displaySubreddits(subreddits) {
        console.log('Displaying subreddits:', subreddits);
        var $results = $('#subreddit-results');
        $results.empty();

        if (subreddits.length === 0) {
            $results.append('<p>No subreddits found for this industry.</p>');
            $('#industry-form').show();
        } else {
            var $form = $('<form id="subreddit-selection-form"></form>');
            var $subwrap = $('<div id="subreddit-wrapper"></div>');
            $form.append('<h3>Select 3 Industry Subreddits:</h3>');

            subreddits.forEach(function(subreddit) {
                $subwrap.append(
                    '<div>' +
                    '<input type="checkbox" name="subreddit" value="' + subreddit + '" id="' + subreddit + '">' +
                    '<label for="' + subreddit + '">' + subreddit + '</label>' +
                    '</div>'
                );
            });
            $form.append('</div>');

            $form.append($subwrap);

            $form.append('<button type="submit" class="btn btn-primary">Find Industry Complaints</button>');
            $results.append($form);
        }

        $results.show();
    }

    let selectedComplaints = [];

    function renderComplaints(data) {
        const complaints = data.top_complaints;

        hideSpinner();

        if(complaints.length === 0) {
            $('#complaints-display').html('<p class="alert alert-warning">no complaints could be found, please refresh the page and try again.</p>');
            return;
        }

        let html = '<form id="complaints-form"><h2>Select up to 3 complaints:</h2><div id="complaint-wrapper">';
        complaints.forEach((complaint, index) => {
            const isChecked = selectedComplaints.some(c => c.index === index);
            const isDisabled = selectedComplaints.length >= 3 && !isChecked;
            html += `
            <div>
                <input type="checkbox" id="complaint-${index}" 
                       ${isChecked ? 'checked' : ''} 
                       ${isDisabled ? 'disabled' : ''}>
                <label for="complaint-${index}">${complaint.topic}</label>
                <ul>
                    ${complaint.expressions.map(expr => `<li>${expr}</li>`).join('')}
                </ul>
            </div>
        `;
        });

        html += `
            </div><div class="wrap-description form-floating">
                <textarea class="form-control" rows="5" name="description" id="description" placeholder="Add your product or service description." required></textarea>
                <label for="description">Add your product or service description.</label>
            </div>
        `;
        html += '<button id="submit-complaints" class="btn btn-primary">Generate Ad Copy</button>';
        html += '</form>';

        $('#complaints-display').html(html);

        // Set up validation for the description textarea
        const validateDescription = validateInput('#description');

        // Add event listeners
        complaints.forEach((complaint, index) => {
            $(`#complaint-${index}`).on('change', function() {
                if (this.checked) {
                    if (selectedComplaints.length < 3) {
                        selectedComplaints.push({
                            index: index,
                            topic: complaint.topic,
                            expressions: complaint.expressions
                        });
                    } else {
                        this.checked = false;
                    }
                } else {
                    selectedComplaints = selectedComplaints.filter(c => c.index !== index);
                }
            });
        });

        $('#submit-complaints').on('click', function(e) {
            e.preventDefault();
            if (!validateDescription()) {
                return;
            }
            const description = $('#description').val().trim();
            const analysis = {
                selectedComplaints: selectedComplaints.map(complaint => ({
                    topic: complaint.topic,
                    expressions: complaint.expressions
                })),
                description: description
            };
            displayAnalysis(analysis);
        });
    }

    function displayAnalysis(data) {
        console.log(data)
        var $results = $('#complaints-display');
        $results.empty();
        $results.html(`<p>Generating ad copy for your complaints. Please wait...</p>`);

        $.ajax({
            url: howdoisellthis_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_ad_copy',
                nonce: howdoisellthis_ajax.nonce,
                data: JSON.stringify(data)
            },
            success: function(response) {
                if (response.success) {
                    displayAdCopy(response.data);
                } else {
                    $results.html(`<p>Error: ${response.data.message}</p>`);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $results.html(`<p>Error: ${textStatus}</p>`);
            }
        });
    }

    function displayAdCopy(adCopyData) {
        //TODO: {"success":true,"data":[]} if the response data is empty, tell the user something went wrong, try again.
        var $results = $('#complaints-display');
        $results.empty();

        var html = '<h2>Generated Google Ad Copy</h2>';

        adCopyData.forEach(function(item) {
            html += `<div class="ad-copy">
            <h3>Complaint: ${item.complaint}</h3>
            <p><strong>Headline:</strong> ${item.ad_copy.headline}</p>
            <p><strong>Description 1:</strong> ${item.ad_copy.description1}</p>
            <p><strong>Description 2:</strong> ${item.ad_copy.description2}</p>
            <p><strong>Call to Action:</strong> ${item.ad_copy.callToAction}</p>
        </div>`;
        });

        $results.html(html);
    }

    $(document).on('submit', '#subreddit-selection-form', function(e) {
        e.preventDefault();
        var selectedSubreddits = $('input[name="subreddit"]:checked').map(function() {
            return this.value;
        }).get();

        if (selectedSubreddits.length !== 3) {
            //TODO: display error on screen instead of alert
            displayError('Please select exactly 3 subreddits.');
            return;
        }

        console.log('Selected subreddits:', selectedSubreddits);

        $('#subreddit-results').hide();
        showSpinner();

        $.ajax({
            url: howdoisellthis_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_comments',
                nonce: howdoisellthis_ajax.nonce,
                subreddits: selectedSubreddits
            },
            success: function(response) {
                $('#searching-comments').hide();
                if (response.success) {
                    console.log('Comments found:', response.data.comments);
                    renderComplaints(response.data);
                } else {
                    console.error('Error fetching comments:', response.data.message);
                    $('#complaints-display').html('<p>Error: ' + response.data.message + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#searching-comments').hide();
                console.error('AJAX error:', textStatus, errorThrown);
                $('#complaints-display').html('<p>Error connecting to the server. Please try again later.</p>');
            }
        });
    });
});
