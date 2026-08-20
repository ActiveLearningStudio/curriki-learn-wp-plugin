<?php
    global $treks_src;
    if (!empty($args['district_post'])) {        
        $edlink_access_token = get_post_meta($args['district_post']->ID, 'lxp_edlink_provider_access_token', true);        
    } else {
        $edlink_access_token = (isset($_GET['district_id']) && isset($_GET['district_id']) > 0) ? get_post_meta($_GET['district_id'], 'lxp_edlink_provider_access_token', true) : '';        
    }
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
    /* .multi-select-container {
        border: 1px solid #ccc;
        width: 250px;
        padding: 5px;
    } */
    .teacher-multi-select-dropdown {
        max-height: 250px;
        overflow-y: auto;
        border: 1px solid #ccc;
        display: none;
        background-color: white;
    }
    .teacher-multi-select-item {
        padding: 5px;
    }
    .teacher-multi-select-search {
        width: 100%;
        padding: 5px;
        margin-bottom: 5px;
    }
    .teacher-multi-select-count {
        font-weight: bold;
    }
</style>

<!-- Modal -->
<div class="modal fade teachers-modal" id="edlinkTeacherModal" tabindex="-1" aria-labelledby="edlinkTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-title">
                    <h2 class="modal-title" id="edlinkTeacherModalLabel"><span class="teacher-action-head">New</span> Teacher</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="row g-3" id="edlinkTeacherForm">
                    <!-- Check Logged User Role -->
                    <?php
                        if (isset($args['role']) && $args['role'] == 'school') {
                            $school_post = lxp_get_user_school_post();
                            $edlink_school_id = get_post_meta($school_post->ID, 'lxp_edlink_school_id', true);
                    ?>
                            <input type="hidden" name="edlink_school_admin_id" value="<?php echo get_current_user_id(); ?>">
                            <input type="hidden" name="edlink_teacher_school_id" value="<?php echo $school_post->ID; ?>">
                    <?php        
                        } else {
                            $edlink_school_id = get_post_meta($_GET['school_id'], 'lxp_edlink_school_id', true);
                    ?>
                            <input type="hidden" name="edlink_school_admin_id" value="<?php echo get_post_meta($_GET['school_id'], 'lxp_school_admin_id', true); ?>">
                            <input type="hidden" name="edlink_teacher_school_id" value="<?php echo $_GET['school_id']; ?>">
                    <?php        
                        }
                    ?>
                    <input type="hidden" name="edlink_teacher_post_id" id="edlink_teacher_post_id" value="0">
                    <div class="label_box" id="edlink_loader" style="color: #0000ff"></div>
                    <div class="label_box" id="edlink_error" style="color: #dc3545"></div>
                    <div class="input_section">
                        <div id="edlink_edit_teacher_container">
                            <div class="input_section">
                                <div class="input_box brief_input_box">
                                    <div class="label_box brief_label_box id_label_box">
                                        <label class="label">Edlink Teachers</label>
                                        <select id="edlinkInputTeacherName" name="edlink_teachers" class="form-select">
                                        </select>
                                    </div>
                                </div>
                            </div><br/>
                            <div class="input_box brief_input_box">
                                <div class="label_box brief_label_box">
                                    <label class="label">About</label>
                                    <input class="brief_info form-control" type="text" name="about" id="about"
                                        placeholder="Enter a brief description here" />
                                </div>
                            </div>
                        </div>
                        <div class="input_box">
                            <div class="multi-select-container" id="edlink_add_teacher_container">
                                <div class="multi-select-header">
                                    <button type="button" class="btn">Edlink Teachers</button>
                                    <span class="teacher-multi-select-count">0 selected</span>
                                    <span class="teacher-multi-select-limit"></span>
                                </div>                                
                                <input type="text" class="teacher-multi-select-search multi-select-toggle" placeholder="Search...">
                                <div class="teacher-multi-select-dropdown">
                                    <!-- Single Select/Unselect All checkbox -->
                                    <div class="teacher-multi-select-item">
                                        <input type="checkbox" id="teacher-select-unselect-all"> Select/Unselect All
                                    </div>
                                    <div id="edlink-teacher-multi-select-container">
                                    </div>
                                </div>
                            </div>
                        </div>                        
                    </div>
                    <div class="horizontal_line"></div>
                    <div class="input_section">
                        <h3 class="modal-title assign-text">Assign Grades</h3>
                        <?php
                            // Rendered from the canonical list rather than hand-written
                            // markup. The hardcoded version stopped at 9th, so a teacher
                            // who signed themselves up teaching 10th-12th had no checkbox
                            // to represent those grades — and re-saving here silently
                            // dropped them. See lxp_get_grade_options() in tl-constants.php.
                            $lxp_grade_options = function_exists('lxp_get_grade_options') ? lxp_get_grade_options() : array();
                            $lxp_grade_index   = 0;
                        ?>
                        <div class="input_box brief_input_box">
                            <?php foreach ($lxp_grade_options as $lxp_grade) :
                                $lxp_grade_index++;
                                $lxp_grade_id = 'gradeCheckbox' . $lxp_grade_index;
                            ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input grade-checkbox" type="checkbox" name="grades[]" id="<?php echo esc_attr($lxp_grade_id); ?>" value="<?php echo esc_attr($lxp_grade); ?>">
                                <label class="form-check-label" for="<?php echo esc_attr($lxp_grade_id); ?>"><?php echo esc_html($lxp_grade); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="btn_box">
                            <button class="btn" type="button" data-bs-dismiss="modal"
                                aria-label="Close">Cancel</button>
                            <button class="btn" id="edlinkSaveTeacherBtn"><span class="teacher-action">Add</span></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    selectionLimit = 10;
    // Listen for individual checkbox changes to update the count
    function teacherMultiSelectChange() {
        teacherUpdateSelectedCount();
        // If all items are selected, check the "Select/Unselect All" box; if any are unchecked, uncheck it
        const allChecked = jQuery('.teacher-multi-select-option').length === jQuery('.teacher-multi-select-option:checked').length;
        jQuery('#teacher-select-unselect-all').prop('checked', allChecked);
    }

    // Update count of selected items
    function teacherUpdateSelectedCount() {
        const selectedCount = jQuery('.teacher-multi-select-option:checked').length;
        jQuery('.teacher-multi-select-count').text(`${selectedCount} selected`);
        //jQuery('.teacher-multi-select-limit').text(`out of ${selectionLimit} maximum`);
        jQuery("#edlinkSaveTeacherBtn").attr("disabled", false);

        if (selectedCount >= selectionLimit) {
            jQuery('.teacher-multi-select-option:not(:checked)').prop('disabled', true);
        } else {
            jQuery('.teacher-multi-select-option').prop('disabled', false);
        }

        // Update "Select/Unselect All" checkbox state based on individual selections
        // const allChecked = jQuery('.teacher-multi-select-option').length === selectedCount;
        // jQuery('#teacher-select-unselect-all').prop('checked', allChecked);
    }

    jQuery(document).ready(function() {

        // Toggle the dropdown display
        jQuery('.multi-select-toggle').on('click', function() {
            jQuery('.teacher-multi-select-dropdown').toggle();
        });

        // Initial count update
        teacherUpdateSelectedCount();

        // Select/Unselect All functionality with selection limit
        jQuery('#teacher-select-unselect-all').on('change', function() {
            const isChecked = jQuery(this).is(':checked');
            const checkboxes = jQuery('.teacher-multi-select-option');
            
            // Determine how many checkboxes can still be checked based on the selection limit
            const currentSelectedCount = checkboxes.filter(':checked').length;
            const availableToSelect = selectionLimit - currentSelectedCount;

            if (isChecked) {
                // Only select up to the limit
                checkboxes.each(function(index) {
                    if (index < availableToSelect || jQuery(this).is(':checked')) {
                        jQuery(this).prop('checked', true);
                    }
                });
            } else {
                // Uncheck all if "Select/Unselect All" is unchecked
                checkboxes.prop('checked', false);
            }

            teacherUpdateSelectedCount();
        });

        // Filter items based on search input
        jQuery('.teacher-multi-select-search').on('input', function() {
            const searchValue = jQuery(this).val().toLowerCase();
            jQuery('.teacher-multi-select-item').each(function() {
                const itemText = jQuery(this).text().toLowerCase();
                jQuery(this).toggle(itemText.includes(searchValue));
            });
        });

        // Close the dropdown when clicking outside
        jQuery(document).on('click', function(event) {
            if (!jQuery(event.target).closest('.multi-select-container').length) {
                jQuery('.teacher-multi-select-dropdown').hide();
            }
        });

        let host = window.location.hostname === 'localhost' ? window.location.origin + '<?php echo WORDPRESS_HOST; ?>' : window.location.origin;
        let apiUrl = host + '/wp-json/lms/v1/';

        var edlinkTeacherModal = document.getElementById('edlinkTeacherModal');
        edlinkTeacherModalObj = new bootstrap.Modal(edlinkTeacherModal);
        window.edlinkTeacherModalObj = edlinkTeacherModalObj;

        jQuery("#addEdlinkTeachers").on('click', function() {
            getEdlinkTeachers();
            jQuery("#edlink_add_teacher_container").show();
            jQuery("#edlink_edit_teacher_container").hide();
            edlinkTeacherModalObj.show();
        });

        let edlinkTeacherForm = jQuery("#edlinkTeacherForm");
        jQuery(edlinkTeacherForm).on('submit', function(e) {
            jQuery("#edlinkSaveTeacherBtn").attr("disabled", true);
            jQuery(".teacher-action").html(`<i class="fa fa-spinner fa-spin" style="font-size:25px"></i> Add`);
            e.preventDefault();
            const formData = new FormData(e.target);
            $.ajax({
                method: "POST",
                enctype: 'multipart/form-data',
                url: apiUrl + "edlink/teachers/save",
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
            }).done(function( response ) {
                jQuery('#edlinkTeacherForm .form-control').removeClass('is-invalid');
                edlinkTeacherModalObj.hide();
                jQuery("#edlinkSaveTeacherBtn").attr("disabled", false);
                jQuery(".teacher-action").text(`Add`);
                window.location.reload();
            }).fail(function (response) {
                jQuery('#edlinkTeacherForm .form-control').removeClass('is-invalid');
                if (response.responseJSON !== undefined) {
                    Object.keys(response.responseJSON.data.params).forEach(element => {                        
                        if (element == 0) {
                            jQuery('.teacher-multi-select-search').css(
                                {
                                    "border": "1px solid #dc3545"
                                }
                            );
                        }
                        if (element == 'edlink_teachers') {                            
                            jQuery('#edlinkInputTeacherName').css(
                                {
                                    "border-color": "#dc3545"
                                }
                            );
                        }
                        jQuery('#edlinkTeacherModal input[name="' + element + '"]').addClass('is-invalid');
                        jQuery('#edlinkTeacherModal textarea[name="' + element + '"]').addClass('is-invalid');
                    });
                }
                jQuery(".teacher-action").text(`Add`);
            });
        
        });

        edlinkTeacherModal.addEventListener('hide.bs.modal', function (event) {
            jQuery('#edlinkTeacherForm .form-control').removeClass('is-invalid');
            jQuery('.teacher-multi-select-search').removeAttr("style");
            jQuery("#edlink_teacher_post_id").val(0);            
            jQuery("#edlink-teacher-multi-select-container").html('');
            jQuery('#teacher-select-unselect-all').prop('checked', false);
            jQuery('#edlinkTeacherModal #about').val();
            jQuery("#edlink_loader").html('');
            jQuery("#edlink_error").html("");
            jQuery('#edlinkTeacherModal input.grade-checkbox').prop('checked', false);
            teacherUpdateSelectedCount();
            jQuery("#edlinkInputTeacherName").html('<option value="0"> Choose...</option>');
            jQuery('.teacher-action').text("Add");
            jQuery('.teacher-action-head').text("New");
            jQuery('.teacher-multi-select-limit').text('');
        });
    });

    function onEdlinkTeacherEdit(teacher_id) {
        jQuery("#edlink_edit_teacher_container").show();
        jQuery("#edlink_add_teacher_container").hide();        
        jQuery("#edlinkSaveTeacherBtn").attr("disabled", true);
        jQuery(".teacher-action").text("Update");
        jQuery(".teacher-action-head").text("Update");
        jQuery("#edlinkInputTeacherName").html('<option value="0"> Choose...</option>');
        
        let host = window.location.hostname === 'localhost' ? window.location.origin + '<?php echo WORDPRESS_HOST; ?>' : window.location.origin;
        let apiUrl = host + '/wp-json/lms/v1/';

        $.ajax({
            method: "POST",
            enctype: 'multipart/form-data',
            url: apiUrl + "teachers",
            data: {teacher_id}
        }).done(function( response ) {
            let teacher = response.data.teacher;
            let admin = response.data.admin.data;
            getEdlinkTeacherForEdit('<?php echo $edlink_access_token; ?>', admin);
            jQuery("#edlinkTeacherModal #edlink_teacher_post_id").val(teacher_id);
            jQuery('#edlinkTeacherModal #about').val(teacher.post_content);
            if (teacher.grades) {
                teacher.grades.forEach(grade => jQuery('#edlinkTeacherModal input.grade-checkbox[value=' + grade +']').prop('checked', true));
            }
            jQuery("#edlinkSaveTeacherBtn").attr("disabled", false);
            edlinkTeacherModalObj.show();
        }).fail(function (response) {
            jQuery("#edlinkSaveTeacherBtn").attr("disabled", true);
            console.error("Can not load teacher");
        });
    }

    function getEdlinkTeachers() {
        jQuery("#edlinkSaveTeacherBtn").attr("disabled", true);
        jQuery("#edlinkInputTeacherName").html('');
        jQuery("#edlink_loader").html('<i class="fa fa-spinner fa-spin" style="font-size:25px"></i> Loading ...');
        var access_token = '<?php echo $edlink_access_token; ?>';
        var edlink_school_id = '<?php echo $edlink_school_id; ?>';
        $.ajax({
            method: "POST",            
            url: apiUrl + "edlink/provider/people-by-role",
            data: {access_token, "role" : "teacher", "school_id" : edlink_school_id}
        }).done(function( response ) {
            // Set Teacher Data
            if (typeof response.people === 'object' && response.people !== null && !response['people']['error']) {
                selectionLimit = Object.keys(response.people).length;
                jQuery('.teacher-multi-select-limit').text(`out of ${selectionLimit}`);
                var html = '';     
                Object.entries(response.people).forEach(([key, person]) => {
                    var val = ''+person["first_name"]+'|'+person["last_name"]+'|'+person["email"]+'';
                    html += '<div class="teacher-multi-select-item">';
                    html += '<input type="checkbox" name="edlink_teachers[]" class="teacher-multi-select-option" onChange="javascript:teacherMultiSelectChange();" value="'+val+'" id="'+person["id"]+'" firstName="'+person["first_name"]+'" lastName="'+person["last_name"]+'"> '+person["first_name"]+' '+person["last_name"]+' ('+person["email"]+')';
                    html += '</div>';
                });
                jQuery("#edlink-teacher-multi-select-container").html(html);
                jQuery("#edlinkSaveTeacherBtn").attr("disabled", false);
            } else {
                jQuery("#edlinkSaveTeacherBtn").attr("disabled", true);                
                jQuery("#edlink_error").html(response['people']['error']);
                jQuery("#edlink-teacher-multi-select-container").html('');
            }
            jQuery("#edlink_loader").html('');
        });
    }

    function getEdlinkTeacherForEdit(access_token, admin) {
        jQuery("#edlink_loader").html('<i class="fa fa-spinner fa-spin" style="font-size:25px"></i> Loading ...');
        var user_email = admin.user_email;
        var edlink_school_id = '<?php echo $edlink_school_id; ?>';
        $.ajax({
            method: "POST",            
            url: apiUrl + "edlink/provider/people/edit",
            data: {access_token, user_email, "role" : "teacher", "school_id" : edlink_school_id}
        }).done(function( response ) {
            // Set Teacher Data
            if (typeof response.people === 'object' && response.people !== null && !response['people']['error']) {
                selectionLimit = Object.keys(response.people).length;
                jQuery('.teacher-multi-select-limit').text(`out of ${selectionLimit}`);
                var html = '';
                html += '';
                Object.entries(response.people).forEach(([key, person]) => {
                    var val = ''+person["first_name"]+'|'+person["last_name"]+'|'+person["email"]+'';
                    html += '<option value="'+val+'">'+person["first_name"]+' '+person["last_name"]+' ('+person["email"]+')</option>';
                });
                jQuery("#edlinkInputTeacherName").html(html);
                var user_cred = ''+admin.first_name+'|'+admin.last_name+'|'+admin.user_email+'';
                jQuery('#edlinkInputTeacherName').val(user_cred);
                jQuery("#edlinkSaveTeacherBtn").attr("disabled", false);
            } else {
                jQuery("#edlinkSaveTeacherBtn").attr("disabled", true);                        
                jQuery("#edlink_error").html(response['people']['error']);
                jQuery("#edlinkInputTeacherName").html('<option value="0"> Choose...</option>');
            }
            jQuery("#edlink_loader").html('');
        });
    }
    
</script>