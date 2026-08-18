<?php
    global $treks_src;
    $district_post = $args['district_post'];
?>

<style>
    .school_input_box {
        padding: 0 0 30px !important;
    }
</style>

<!-- Modal -->
<div class="modal fade modal-lg" id="schoolModal" tabindex="-1" aria-labelledby="schoolModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <div class="modal-header-title">
                    <h2 class="modal-title" id="exampleModalLabel"><span class="school-action">New</span> School</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <form class="row g-3" id="schoolForm">            
                    <input type="hidden" name="district_admin_id" value="<?php echo get_post_meta($district_post->ID, 'lxp_district_admin', true); ?>">
                    <input type="hidden" name="school_district_id" value="<?php echo $district_post->ID; ?>">
                    <input type="hidden" name="school_post_id" id="school_post_id" value="0">
                    <div class="input_section">
                        <div class="personal_box">
                            <p class="personal-text">School</p>
                            <!-- <div class="info_box">
                                <img class="action_logo" src="<?php // echo $treks_src; ?>/assets/img/import.svg" alt="logo" />
                                <p class="personal-text import-text">Import info</p>
                            </div> -->
                        </div>
                        <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box id_label_box">
                                <label class="label">School name</label>
                                <input class="brief_info id_info form-control" type="text" id="inputSchoolName" name="school_name" placeholder="Enter school’s name here" />
                            </div>
                        </div>
                        <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box">
                                <label class="label">About</label>
                                <input class="brief_info form-control" type="text" id="inputAbout" name="school_about" placeholder="Enter a brief description here" />
                            </div>
                        </div>
                        <!-- Zero-PII enrollment (COPPA/FERPA) -->
                        <input type="hidden" name="lxp_school_privacy_controls" value="1">
                        <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box">
                                <label class="label">Student privacy</label>
                                <div class="form-check" style="padding-top:6px">
                                    <input class="form-check-input" type="checkbox" value="1" id="inputSchoolTokenMode" name="lxp_school_token_mode">
                                    <label class="form-check-label" for="inputSchoolTokenMode" style="font-size:13px">
                                        Privacy-preserving enrollment (no student names stored)
                                    </label>
                                </div>
                                <small class="text-muted" style="font-size:12px">
                                    Students join with a class code and a seat label. Turning this on
                                    disables CSV import and manual student creation for this school.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="horizontal_line"></div>
                    <div class="input_section" style="margin-bottom: 25px;">
                        <p class="personal-text">Administrator</p>
                    </div>
                
                    <div class="personal_box user-profile-box" style="display:none;">
                        <img src="<?php echo $treks_src; ?>/assets/img/user-profile.svg" alt="logo" />
                        <div class="info_box">
                            <img class="action_logo" src="<?php echo $treks_src; ?>/assets/img/upload.svg" alt="logo" />
                            <input type="file" name="profile_picture" id="profile_picture">
                            <p class="personal-text import-text">Upload</p>
                        </div>
                        <div class="info_box">
                            <img class="action_logo" src="<?php echo $treks_src; ?>/assets/img/delete-circled-outline.svg"
                                alt="logo" />
                            <p class="personal-text import-text remove_text">Remove</p>
                        </div>
                    </div>
                
                    <div class="input_section school_input_box">
                        <div class="input_box">
                            <div class="label_box">
                                <label class="label">First name</label>
                                <input class="form-control" type="text" id="inputFirstName" name="first_name" placeholder="Enter teacher’s first name here" />
                            </div>
                        </div>
                        <div class="input_box">
                            <div class="label_box">
                                <label class="label">Last name</label>
                                <input class="form-control" type="text" id="inputLastName" name="last_name" placeholder="Enter teacher’s last name here" />
                            </div>
                        </div>
                        <div class="input_box">
                            <div class="label_box">
                                <label class="label">Email</label>
                                <input class="form-control" type="email" id="inputEmail" name="user_email" placeholder="johndoe@gmail.com" />
                                <input type="hidden" id="inputEmailDefault" name="user_email_default" />
                            </div>
                        </div>
                        <div class="input_box">
                            <div class="label_box">
                                <label class="label">Password</label>
                                <input class="form-control" type="password" id="inputPassword" name="user_password" placeholder="***" />
                            </div>
                        </div>
                        
                        <!-- <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box id_label_box">
                                <label class="label">ID</label>
                                <input class="brief_info id_info" type="password" name="password"
                                    placeholder="0000" />
                            </div>
                        </div>
                        -->
                    </div>
                </form>
                <div class="input_section">
                    <div class="btn_box">
                        <button class="btn" type="button" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
                        <button class="btn" id="saveSchoolBtn"><span class="school-action">Add</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    
    jQuery(document).ready(function() {
        let host = window.location.hostname === 'localhost' ? window.location.origin + '<?php echo WORDPRESS_HOST; ?>' : window.location.origin;
        let apiUrl = host + '/wp-json/lms/v1/';

        var schoolModal = document.getElementById('schoolModal');
        schoolModalObj = new bootstrap.Modal(schoolModal);
        window.schoolModalObj = schoolModalObj;

        jQuery("#addSchoolModal").on('click', function() {
            schoolModalObj.show();
        });

        jQuery("#saveSchoolBtn").on('click', function() {
            jQuery("#schoolForm").submit();
        });
        
        let schoolForm = jQuery("#schoolForm");
        jQuery(schoolForm).on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            jQuery("#saveSchoolBtn").attr("disabled", "disabled");
            let beforeText = jQuery("#saveSchoolBtn").text();
            jQuery("#saveSchoolBtn").html(`<i class="fa fa-spinner fa-spin"></i> ` + beforeText);
            
            $.ajax({
                method: "POST",
                enctype: 'multipart/form-data',
                url: apiUrl + "shools/save",
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
            }).done(function( response ) {
                jQuery('#schoolForm .form-control').removeClass('is-invalid');
                schoolModalObj.hide();
                window.location.reload();
            }).fail(function (response) {
                jQuery('#schoolForm .form-control').removeClass('is-invalid');
                if (response.responseJSON !== undefined) {
                    Object.keys(response.responseJSON.data.params).forEach(element => {
                        jQuery('input[name="' + element + '"]').addClass('is-invalid');
                        jQuery('textarea[name="' + element + '"]').addClass('is-invalid');
                    });
                }
                jQuery("#saveSchoolBtn").text(beforeText);
                jQuery("#saveSchoolBtn").removeAttr("disabled");
            });
        
        });

        schoolModal.addEventListener('hide.bs.modal', function (event) {
            jQuery("#school_post_id").val(0);
            jQuery('#inputSchoolTokenMode').prop('checked', false);
            jQuery('#inputSchoolName').val("");
            jQuery('#inputAbout').val("");
            jQuery('#inputFirstName').val("");
            jQuery('#inputLastName').val("");
            jQuery('#inputEmail').val("");
            jQuery('#inputEmailDefault').val("");
            jQuery('#inputPassword').val("");
            jQuery('.school-action').text("Add");
            jQuery('#schoolForm .form-control').removeClass('is-invalid');
            //window.location.reload();
        });
    });
    
</script>