<?php
    global $treks_src;
    global $userdata;

    $teacher_post = lxp_get_teacher_post($userdata->data->ID);
    $teacher_school_id = get_post_meta($teacher_post->ID, 'lxp_teacher_school_id', true);
    $school_post = get_post($teacher_school_id);
    $students = lxp_get_school_teacher_students_active($teacher_post->ID);
    $students = array_filter($students, function($student) use ($teacher_post) {
        return in_array($teacher_post->ID, get_post_meta($student->ID, 'lxp_teacher_id', true));
    });
    // $students = lxp_get_school_students($teacher_school_id);
    // $students = array_filter($students, function($student) use ($teacher_post) {
    //     return get_post_meta($student->ID, 'lxp_teacher_id', true) == $teacher_post->ID;
    // });
    //$classes = lxp_get_teacher_classes($teacher_post->ID);
    $default_classes = lxp_get_teacher_default_classes($teacher_post->ID);
    $classes = lxp_get_teacher_group_by_type($teacher_post->ID, 'classes');
    $other_groups = lxp_get_teacher_group_by_type($teacher_post->ID, 'other_group');
    $classes = array_merge($default_classes, $classes);
    // edlink district stting
    $district_post = get_post(get_post_meta($school_post->ID, 'lxp_school_district_id', true));
    $district_type = get_post_meta($district_post->ID, 'lxp_district_type', true);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Classes & Groups</title>
    <link href="<?php echo $treks_src; ?>/style/main.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/header-section.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/schoolAdminTeachers.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/addNewTeacherModal.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/schoolDashboard.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/schoolAdminStudents.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/adminInternalTeacherView.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/teacherStudentsClasses.css" />
    <link rel="stylesheet" href="<?php echo $treks_src; ?>/style/newAssignment.css" />
    <link href="<?php echo $treks_src; ?>/style/treksstyle.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css"
        integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <style type="text/css">
        .heading-wrapper {
            border: 0px solid red;
            height: 145px;
        }

        .heading-left {
            float: left;
        }
        .heading-right {
            padding-top: 70px;
            padding-right: 20px;
            float: right;
        }

        .students-table .table tbody tr td .dropdown .dropdown-menu.show {
            width: 200px;
        }
    </style>
</head>

<body>

    <!-- Header Section -->
    <nav class="navbar navbar-expand-lg bg-light">
        <div class="container-fluid">
            <?php include $livePath.'/trek/header-logo.php'; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <div class="navbar-nav me-auto mb-2 mb-lg-0">
                    <div class="header-logo-search">
                        <!-- searching input -->
                        <div class="header-search">
                            <img src="<?php echo $treks_src; ?>/assets/img/header_search.svg" alt="svg" />
                            <form action="<?php echo site_url("search"); ?>">
                                <input placeholder="Search" id="q" name="q" value="<?php echo isset($_GET["q"]) ? $_GET["q"]:''; ?>" />
                            </form>
                        </div>
                    </div>
                </div>
                <div class="d-flex" role="search">
                    <div class="header-notification-user">
                        <?php include $livePath.'/trek/user-profile-block.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Nav Section -->
    <section class="main-container">
        <nav class="nav-section">
            <?php include $livePath.'/trek/navigation.php'; ?>
        </nav>
    </section>

    <!-- Welcome: section-->
    <section class="welcome-section">
        <!-- Welcome: heading-->
        <div class="heading-wrapper">
            <div class="heading-left">
                <div class="welcome-content">
                    <h2 class="welcome-heading">Classes & Groups</h2>
                    <p class="welcome-text">Student enrollment and registration management</p>
                </div>
            </div>

            <div class="heading-right">
                <a href="<?php echo site_url("students"); ?>" type="button" class="btn btn-outline-secondary btn-lg">Students</a>
                <a href="<?php echo site_url("classes"); ?>" type="button" class="btn btn-secondary btn-lg">Classes</a>
                <a href="<?php echo site_url("groups"); ?>" type="button" class="btn btn-outline-secondary btn-lg">Groups</a>
            </div>
        </div>

        <!-- Total Schools: section-->
        <section class="school-section">
            <section class="school_teacher_cards">
                <div class="add-teacher-box">
                    <div class="search-filter-box">
                        <div class="search_box">
                            <label class="search-label">Search</label>
                            <input type="text" name="text" placeholder="School, ID, admin" />
                        </div>
                        <div class="filter-box">
                            <img src="<?php echo $treks_src; ?>/assets/img/filter-alt.svg" alt="filter logo" />
                            <p class="filter-heading">Filter</p>
                        </div>
                    </div>                    
                    <button id="classModalBtn" class="add-heading" type="button" data-bs-toggle="modal" data-bs-target="#classModal" class="primary-btn">
                        Add New Class & Groups
                    </button>
                </div>

                <!-- Classes Section -->
                <section class="recent-treks-section-div table-school-section">

                    <div class="students-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="">
                                        <div class="th1">
                                            Class
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th2">
                                            Schedule
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th3">
                                            Assignments
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th4">
                                            Grades
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1">
                                            Courses
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1">
                                            Code
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1">
                                            Seats
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th5">
                                            Groups
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    foreach ($classes as $class) {
                                        $lxp_class_code = get_post_meta($class->ID, 'lxp_class_code', true);
                                        $lxp_seats_max  = (int) get_post_meta($class->ID, 'lxp_class_max_seats', true);
                                        $lxp_seats_used = lxp_get_class_seats_taken($class->ID);
                                ?>
                                    <tr>
                                        <td class="user-box">
                                            <div class="table-user">
                                                <img src="<?php echo $treks_src; ?>/assets/img/profile-icon.png" alt="student" />
                                                <div class="user-about">
                                                    <h5><?php echo $class->post_title?></h5>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-status grade">
                                                <?php
                                                    $schedule = (array)json_decode(get_post_meta($class->ID, 'schedule', true));
                                                    foreach (array_keys($schedule) as $day) {
                                                        $start = date('h:i a', strtotime($schedule[$day]->start));
                                                        $end = date('h:i a', strtotime($schedule[$day]->end));
                                                    ?>
                                                        <span><?php echo ucwords($day) ?> / <?php echo $start; ?> - <?php echo $end; ?></span>
                                                    <?php } ?>
                                            </div>
                                        </td>
                                        <td><?php echo count(lxp_get_class_assignments($class->ID)); ?></td>
                                        <td class="grade">
                                            <span><?php echo get_post_meta($class->ID, 'grade', true); ?></span>
                                        </td>
                                        <td><?php echo count(get_post_meta($class->ID, 'lxp_class_course_ids')) ?: '&mdash;'; ?></td>
                                        <td style="white-space:nowrap">
                                            <?php if ($lxp_class_code) : ?>
                                                <code class="lxp-class-code-tag" style="background:#f1f3f4;padding:2px 7px;border-radius:4px;font-size:12px;letter-spacing:.5px"><?php echo esc_html($lxp_class_code); ?></code>
                                                <button class="lxp-copy-code" data-code="<?php echo esc_attr($lxp_class_code); ?>" title="Copy code" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 4px">&#10697;</button>
                                                <button class="lxp-copy-link" data-code="<?php echo esc_attr($lxp_class_code); ?>" title="Copy share link" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 4px">&#128279;</button>
                                            <?php else : ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap">
                                            <span class="lxp-seat-badge" style="background:#e8f0fe;color:#1967d2;padding:2px 8px;border-radius:10px;font-size:12px">
                                                <?php echo esc_html($lxp_seats_used); ?> / <?php echo $lxp_seats_max > 0 ? esc_html($lxp_seats_max) : '&infin;'; ?>
                                            </span>
                                            <button class="lxp-view-roster" data-class-id="<?php echo esc_attr($class->ID); ?>" data-class-name="<?php echo esc_attr($class->post_title); ?>" title="View roster & claim links" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 4px">&#128203;</button>
                                        </td>
                                        <td>
                                            <?php
                                                echo count(lxp_get_class_group($class->ID));
                                            ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="dropdown_btn" type="button" id="dropdownMenu2"
                                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <img src="<?php echo $treks_src; ?>/assets/img/dots.svg" alt="logo" />
                                                </button>
                                                <div class="dropdown-menu" aria-labelledby="dropdownMenu2">
                                                    <button class="dropdown-item" type="button" onclick="onClassEdit(<?php echo $class->ID; ?>)">
                                                        <img src="<?php echo $treks_src; ?>/assets/img/edit.svg" alt="logo" />
                                                        Edit</button>
                                                    <!-- <button class="dropdown-item" type="button">
                                                        <img src="./assets/img/delete.svg" alt="logo" />
                                                        Delete</button> -->
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Other Groups Section -->
                <section class="recent-treks-section-div table-school-section">

                    <div class="students-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="">
                                        <div class="th1">
                                            Class Group
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th2">
                                            Schedule
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th3">
                                            Assignments
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th4">
                                            Grades
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1">
                                            Courses
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1">
                                            Code
                                        </div>
                                    </th>
                                    <th>
                                        <div class="th1 th5">
                                            Groups
                                            <img src="<?php echo $treks_src; ?>/assets/img/showing.svg" alt="logo" />
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    foreach ($other_groups as $other_group) {
                                        $lxp_group_code = get_post_meta($other_group->ID, 'lxp_class_code', true);
                                ?>
                                    <tr>
                                        <td class="user-box">
                                            <div class="table-user">
                                                <img src="<?php echo $treks_src; ?>/assets/img/profile-icon.png" alt="student" />
                                                <div class="user-about">
                                                    <h5><?php echo $other_group->post_title?></h5>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-status grade">
                                                <?php
                                                    $schedule = (array)json_decode(get_post_meta($other_group->ID, 'schedule', true));
                                                    foreach (array_keys($schedule) as $day) {
                                                        $start = date('h:i a', strtotime($schedule[$day]->start));
                                                        $end = date('h:i a', strtotime($schedule[$day]->end));
                                                    ?>
                                                        <span><?php echo ucwords($day) ?> / <?php echo $start; ?> - <?php echo $end; ?></span>
                                                    <?php } ?>
                                            </div>
                                        </td>
                                        <td><?php echo count(lxp_get_class_assignments($other_group->ID)); ?></td>
                                        <td class="grade">
                                            <span><?php echo get_post_meta($other_group->ID, 'grade', true); ?></span>
                                        </td>
                                        <td><?php echo count(get_post_meta($other_group->ID, 'lxp_class_course_ids')) ?: '&mdash;'; ?></td>
                                        <td style="white-space:nowrap">
                                            <?php if ($lxp_group_code) : ?>
                                                <code class="lxp-class-code-tag" style="background:#f1f3f4;padding:2px 7px;border-radius:4px;font-size:12px;letter-spacing:.5px"><?php echo esc_html($lxp_group_code); ?></code>
                                                <button class="lxp-copy-code" data-code="<?php echo esc_attr($lxp_group_code); ?>" title="Copy code" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 4px">&#10697;</button>
                                                <button class="lxp-copy-link" data-code="<?php echo esc_attr($lxp_group_code); ?>" title="Copy share link" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 4px">&#128279;</button>
                                            <?php else : ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                echo count(lxp_get_class_group($other_group->ID));
                                            ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="dropdown_btn" type="button" id="dropdownMenu2"
                                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <img src="<?php echo $treks_src; ?>/assets/img/dots.svg" alt="logo" />
                                                </button>
                                                <div class="dropdown-menu" aria-labelledby="dropdownMenu2">
                                                    <button class="dropdown-item" type="button" onclick="onClassEdit(<?php echo $other_group->ID; ?>)">
                                                        <img src="<?php echo $treks_src; ?>/assets/img/edit.svg" alt="logo" />
                                                        Edit</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </section>
        </section>
    </section>

    <script src="https://code.jquery.com/jquery-3.6.3.js"
        integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM=" crossorigin="anonymous"></script>
    <script
        src="<?php echo $treks_src; ?>/js/Animated-Circular-Progress-Bar-with-jQuery-Canvas-Circle-Progress/dist/circle-progress.js"></script>
    <script src="<?php echo $treks_src; ?>/js/custom.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
        crossorigin="anonymous"></script>
    
    <script>
        document.querySelectorAll('.lxp-copy-code').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var code = btn.getAttribute('data-code');
                navigator.clipboard.writeText(code).then(function() {
                    var orig = btn.innerHTML;
                    btn.innerHTML = '&#10003;';
                    setTimeout(function() { btn.innerHTML = orig; }, 1500);
                });
            });
        });

        document.querySelectorAll('.lxp-copy-link').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var code = btn.getAttribute('data-code');
                var url = window.location.origin + '/student-courses/?class_code=' + code;
                navigator.clipboard.writeText(url).then(function() {
                    var orig = btn.innerHTML;
                    btn.innerHTML = '&#10003;';
                    setTimeout(function() { btn.innerHTML = orig; }, 1500);
                });
            });
        });
    </script>

    <?php include $livePath.'/lxp/class-roster-modal.php'; ?>

    <?php
        $args['students'] = $students;
        $args['teacher_post'] = $teacher_post;
        if (isset($district_type) && $district_type == 'edlink') {
            $args['school_post'] = $school_post;
            $args['district_post'] = $district_post;
            include $livePath.'/lxp/admin-class-modal.php';
        } else {
            include $livePath.'/lxp/teacher-class-modal.php';
        }
    ?>
</body>

</html>