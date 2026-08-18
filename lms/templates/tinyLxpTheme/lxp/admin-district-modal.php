<style>
    .district_input_box {
        padding: 0 0 30px !important;
    }
</style>

<!-- Modal -->
<div class="modal fade modal-lg" id="districtModal" tabindex="-1" aria-labelledby="districtModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-title">
                    <h2 class="modal-title" id="exampleModalLabel"><span class="district-action">New</span> District</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="row g-3" id="districtForm">
                    <input type="hidden" name="site_admin_id" id="site_admin_id" value="<?php echo get_current_user_id(); ?>">
                    <input type="hidden" name="district_post_id" id="district_post_id" value="0">
                    <div class="input_section">
                        <div class="personal_box">
                            <p class="personal-text">District</p>
                            <!-- <div class="info_box">
                                <img class="action_logo" src="<?php // echo $treks_src; ?>/assets/img/import.svg" alt="logo" />
                                <p class="personal-text import-text">Import info</p>
                            </div> -->
                        </div>
                        <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box id_label_box">
                                <label class="label">District name</label>
                                <input class="brief_info id_info form-control" type="text" id="inputDistrictName" name="district_name" placeholder="Enter district’s name here" />
                            </div>
                        </div>
                        <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box">
                                <label class="label">About</label>
                                <input class="brief_info form-control" type="text" id="inputAbout" name="district_about" placeholder="Enter a brief description here" />
                            </div>
                        </div>
                        <!-- Roster-vault escrow key (Zone B recovery) -->
                        <div class="input_box brief_input_box">
                            <div class="label_box brief_label_box">
                                <label class="label" for="inputEscrowKey">Roster recovery key <small>(optional)</small></label>
                                <textarea class="brief_info form-control" id="inputEscrowKey" name="lxp_district_escrow_pubkey" rows="4"
                                          placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"
                                          style="font-family:monospace;font-size:11px"></textarea>
                                <small class="text-muted" style="font-size:12px">
                                    An RSA <strong>public</strong> key. Teachers' browsers use it to store a recovery
                                    copy of each class roster key. Keep the matching <strong>private</strong> key in your
                                    own KMS or offline safe — never paste it here. Without this, a teacher who forgets
                                    their passphrase loses that class's student names permanently.
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
                    
                    <div class="input_section district_input_box">
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
                    </div>
                </form>
            </div>                
            <div class="input_section">
                <div class="btn_box">
                    <button class="btn" type="button" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
                    <button class="btn" id="saveDistrictBtn"><span class="district-action">Add</span></button>
                </div>
            </div>
        </div>
    </div>    
</div>

<script type="text/javascript">
    
    jQuery(document).ready(function() {
        let host = window.location.hostname === 'localhost' ? window.location.origin + '<?php echo WORDPRESS_HOST; ?>' : window.location.origin;
        let apiUrl = host + '/wp-json/lms/v1/';

        var districtModal = document.getElementById('districtModal');
        districtModalObj = new bootstrap.Modal(districtModal);
        window.districtModalObj = districtModalObj;

        jQuery("#addDistrictModal").on('click', function() {
            districtModalObj.show();
        });

        jQuery("#saveDistrictBtn").on('click', function() {
            jQuery("#districtForm").submit();
        });
        
        let districtForm = jQuery("#districtForm");
        jQuery(districtForm).on('submit', function(e) {
            e.preventDefault();

            jQuery("#saveDistrictBtn").attr("disabled", "disabled");
            let beforeText = jQuery("#saveDistrictBtn").text();
            jQuery("#saveDistrictBtn").html(`<i class="fa fa-spinner fa-spin"></i> ` + beforeText);
            
            const formData = new FormData(e.target);
            
            $.ajax({
                method: "POST",
                enctype: 'multipart/form-data',
                url: apiUrl + "district/save",
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
            }).done(function( response ) {
                jQuery('#districtForm .form-control').removeClass('is-invalid');
                districtModalObj.hide();
                window.location.reload();
            }).fail(function (response) {
                jQuery('#districtForm .form-control').removeClass('is-invalid');
                if (response.responseJSON !== undefined) {
                    Object.keys(response.responseJSON.data.params).forEach(element => {
                        jQuery('input[name="' + element + '"]').addClass('is-invalid');
                        jQuery('textarea[name="' + element + '"]').addClass('is-invalid');
                    });
                }
                jQuery("#saveDistrictBtn").text(beforeText);
                jQuery("#saveDistrictBtn").removeAttr("disabled");
            });
        
        });

        districtModal.addEventListener('hide.bs.modal', function (event) {
            jQuery("#district_post_id").val(0);
            jQuery('#inputDistrictName').val("");
            jQuery('#inputAbout').val("");
            jQuery('#inputEscrowKey').val("");
            jQuery('#inputFirstName').val("");
            jQuery('#inputLastName').val("");
            jQuery('#inputEmail').val("");
            jQuery('#inputEmailDefault').val("");
            jQuery('#inputPassword').val("");
            jQuery('.district-action').text("Add");
            jQuery('#districtForm .form-control').removeClass('is-invalid');
        });
    });
    
</script>