<?php
    function get_attendance_count($client_id) {
        global $link;
        $count = 0;
        // Prepare a select statement
        $sql = "SELECT count(*) FROM attendance_record WHERE client_id = ?";

        if($stmt = mysqli_prepare($link, $sql)){
            // Set parameters
            $param_id = trim($client_id);
            $__vartype = "i";
            mysqli_stmt_bind_param($stmt, $__vartype, $param_id);

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                $count = mysqli_fetch_array($result)[0];

                if(mysqli_num_rows($result) == 1){
                    /* Fetch result row as an associative array. Since the result set
                    contains only one row, we don't need to use while loop */
                    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
                } else{
                    // URL doesn't contain valid id parameter. Redirect to error page
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.<br>".$stmt->error;
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $count;
    }

    function get_client_victims($client_id) {
        global $link;
        $sql = "
        SELECT
            v.id,
            v.name,
            v.relationship,
            v.gender,
            v.age,
            v.living_with_client,
            v.children_under_18,
            v.address_line1,
            v.address_line2,
            v.city,
            v.state,
            v.zip,
            v.phone,
            v.email
        FROM victim v
        WHERE v.client_id = ?
        ORDER BY v.id
        ";
        $victims = [];
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $victims[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        return $victims;
    }
    
    // Returns an assoc array of informaton about client progress stages
    function get_client_stages() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM client_stage";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about case managers
    function get_case_managers() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM case_manager order by last_name asc, first_name asc, office asc";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }
    
    // ---------------------------------------------------------------------------
    //  Returns id + label for every active therapy group – used by check_in_step4
    // ---------------------------------------------------------------------------
    /* ------------------------------------------------------------------
    * Fetch a list of groups for the Therapy‑Group dropdown.
    * Label format:  “<Group Name> – <Address>”  (address optional)
    * ----------------------------------------------------------------- */
    function get_active_therapy_groups() {
        global $link;
        $groups = [];

        $sql = "
            SELECT
                id,
                /* add the address only if it exists */
                CASE
                    WHEN COALESCE(address,'') = ''
                        THEN name
                    ELSE CONCAT(name, ' – ', address)
                END AS label
            FROM therapy_group
            -- If you later add an  `active`  flag, add  WHERE active = 1  here
            ORDER BY name
        ";

        if ($stmt = mysqli_prepare($link, $sql)) {
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $groups = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                echo "SQL error in get_active_therapy_groups(): {$stmt->error}";
            }
            mysqli_stmt_close($stmt);
        }
        return $groups;
    }



    function get_client_behavior_contract($client_id) {
        global $link;
        $sql = "SELECT behavior_contract_status, behavior_contract_signed_date
                FROM client
                WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            return mysqli_fetch_assoc($result);
        }
        return null;
    }
    

    // Returns an assoc array of informaton about ethnicities
    function get_ethnicities() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM ethnicity";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about gender
    function get_genders() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM gender";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }
    
    // Returns an assoc array of informaton about referral_type
    function get_referral_types() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM referral_type";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about exit_reason
    function get_exit_reasons() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM exit_reason";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about exit_reason
    function get_client_event_types() {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM client_event_type";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about therapy_groups
    function get_therapy_groups($program_id=null) {
        global $link;
        $resultarray = null;

        $stmt = null;
        if($program_id == null){
            $sql = "SELECT * FROM therapy_group tg";
            $stmt = mysqli_prepare($link, $sql);
        }
        else {
            $sql = "SELECT * FROM therapy_group tg WHERE program_id = ?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "i", $program_id);
        }

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about a particular therapy_group
    function get_therapy_group_info($therapy_group_id) {
        global $link;
        $resultarray = null;
        $sql = "SELECT * FROM therapy_group tg WHERE tg.id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $therapy_group_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                $resultarray = mysqli_fetch_assoc($result);
            } else{
                echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
            }
        }
        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    // Returns an assoc array of informaton about programs
    function get_programs() {
        global $link;
        $resultarray = null;

        $sql = "SELECT * FROM program";
        $stmt = mysqli_prepare($link, $sql);

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        } else{
            echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
        }

        mysqli_stmt_close($stmt);
        return $resultarray;
    }


    // Returns an assoc array of informaton about a client's payments
    function get_client_ledger($client_id) {
        global $link;
        $resultarray = [];
        $sql = "SELECT l.id ledger_id, l.amount amount, l.create_date create_date, l.note note from ledger l WHERE l.client_id = ? ORDER BY l.create_date ASC";

        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                $resultarray = $result->fetch_all(MYSQLI_ASSOC);
            } else{
                echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
            }
        }
        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }
    
    // Returns an assoc array of client event
    function get_client_events($client_id) {
        global $link;
        $resultarray = [];
        $sql = "SELECT e.id event_id, et.event_type, e.date date, e.note note from client_event e left join client_event_type et on e.client_event_type_id = et.id WHERE e.client_id = ? ORDER BY e.date ASC";

        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                $resultarray = $result->fetch_all(MYSQLI_ASSOC);
            } else{
                echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
            }
        }
        // Close statement
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

        // Returns an assoc array of client event
        function get_client_event_info($client_event_id) {
            global $link;
            $resultarray = [];
            $sql = "SELECT e.id event_id, et.id event_type_id, et.event_type event_type, e.date event_date, e.note event_note, e.client_id client_id, concat(c.first_name, ' ', c.last_name) client_name
            from client_event e left join client_event_type et on e.client_event_type_id = et.id left join client c ON e.client_id = c.id
            WHERE e.id = ?";
    
            if($stmt = mysqli_prepare($link, $sql)){
                mysqli_stmt_bind_param($stmt, "i", $client_event_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    $resultarray = mysqli_fetch_assoc($result);
                } else{
                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                }
            }
            // Close statement
            mysqli_stmt_close($stmt);
            return $resultarray;
        }
    


    // Returns an assoc array of informaton about a particular therapy_session
    function get_therapy_session_info($therapy_session_id) {
            global $link;
            $resultarray = null;
            $sql = "
            SELECT
            ts.date id,
            ts.date date,
            DATE_FORMAT(date, '%a') weekday,
            ts.duration_minutes duration,
            COALESCE(attendance_count.num_attended, 0) attendance,
            cur.short_description curriculum_short,
            concat (f.first_name, \" \", f.last_name ) facilitator,
            therapy_group_id therapy_group_id,
            tg.name group_name,
            tg.address group_address
        FROM
            therapy_session ts
        LEFT OUTER JOIN curriculum cur ON
            ts.curriculum_id = cur.id
        LEFT OUTER JOIN facilitator f ON
            ts.facilitator_id = f.id
        JOIN therapy_group tg ON
            ts.therapy_group_id = tg.id
        LEFT OUTER JOIN (SELECT therapy_session_id, count(therapy_session_id) num_attended from attendance_record group by therapy_session_id) as attendance_count ON
            ts.id = attendance_count.therapy_session_id
        WHERE
            ts.id = ?";
            
            if($stmt = mysqli_prepare($link, $sql)){
                $__vartype = "i";
                mysqli_stmt_bind_param($stmt, $__vartype, $therapy_session_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    $resultarray = mysqli_fetch_array($result, MYSQLI_ASSOC);
                } else{
                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                }
            }
            // Close statement
            mysqli_stmt_close($stmt);
            return $resultarray;
        }

        function get_session_attendance($therapy_session_id) {
            global $link;
            $resultarray = null;
            $sql = "
            SELECT
                c.id client_id,
                c.first_name,
                c.last_name,
                c.phone_number,
                rt.referral_type,
                ar.note attendance_note
            FROM
                attendance_record ar
            JOIN client c ON
                ar.client_id = c.id
            LEFT join referral_type rt on c.referral_type_id = rt.id
            WHERE
                ar.therapy_session_id = ?
            order by last_name, first_name";
            
            if($stmt = mysqli_prepare($link, $sql)){
                $__vartype = "i";
                mysqli_stmt_bind_param($stmt, $__vartype, $therapy_session_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    while($row = mysqli_fetch_assoc($result)){
                        $resultarray[] = $row;
                    }
                } else{
                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                }
            }
            // Close statement
            mysqli_stmt_close($stmt);
            return $resultarray;
        }
        
        function get_client_info($client_id) {
            global $link;
            $resultarray = null;
        
            // Prepare a select statement
            $sql = "
            SELECT
            c.first_name,
            c.last_name,
            c.date_of_birth,
            DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth)),'%Y') +0 age,
            gend.gender,
            c.birth_place,
            c.intake_packet,
            c.phone_number,
            c.email,
            c.therapy_group_id,
            e.name ethnicity,
            cause_number,
            rt.referral_type referral_type,
            m.id case_manager_id,
            concat(m.first_name, \" \", m.last_name) case_manager,
            m.office po_office,
            g.name group_name,
            orientation_date,
            exit_date,
            er.reason exit_reason,
            exit_note,
            required_sessions sessions_required,
            (select count(*) from attendance_record ar where c.id = ar.client_id) sessions_attended,
            (select count(*) from absence ab where c.id = ab.client_id and ab.excused = '1') absence_excused,
            (select count(*) from absence ab where c.id = ab.client_id and ab.excused <> '1') absence_unexcused,
            stage.id client_stage_id,
            stage.stage client_stage,
            fee fee,
            (select COALESCE(sum(amount),0) from ledger where c.id = ledger.client_id) balance,            
            emergency_contact,
            note client_note,
            case when speaksSignificantlyInGroup = 0 then 'false' else 'true' end as speaksSignificantlyInGroup,
            case when respectfulTowardsGroup = 0 then 'false' else 'true' end as respectfulTowardsGroup,
            case when takesResponsibilityForPastBehavior = 0 then 'false' else 'true' end as takesResponsibilityForPastBehavior,
            case when disruptiveOrArgumentitive = 0 then 'false' else 'true' end as disruptiveOrArgumentitive,
            case when inappropriateHumor = 0 then 'false' else 'true' end as inappropriateHumor,
            case when blamesVictim = 0 then 'false' else 'true' end as blamesVictim,
            case when drug_alcohol = 0 then 'false' else 'true' end as drug_alcohol,
            case when inappropriate_behavior_to_staff  = 0 then 'false' else 'true' end as inappropriate_behavior_to_staff,
            other_concerns other_concerns,
            behavior_contract_status behavior_contract_status,
            behavior_contract_signed_date behavior_contract_signed_date,
            attends_sunday, attends_sunday_t4c, attends_monday, attends_tuesday, attends_wednesday, attends_thursday, attends_friday, attends_saturday
            FROM client
                c
            LEFT OUTER JOIN ethnicity e ON
                c.ethnicity_id = e.id
            LEFT OUTER JOIN referral_type rt ON
                c.referral_type_id = rt.id
            LEFT OUTER JOIN exit_reason er ON
                c.exit_reason_id = er.id
            LEFT OUTER JOIN case_manager m ON
                c.case_manager_id = m.id
            LEFT OUTER JOIN therapy_group g ON
                c.therapy_group_id = g.id
            LEFT JOIN gender gend ON
                c.gender_id = gend.id
            LEFT JOIN client_stage stage ON
                c.client_stage_id = stage.id
            WHERE
                c.id = ?";
            
            if($stmt = mysqli_prepare($link, $sql)){
                $__vartype = "i";
                mysqli_stmt_bind_param($stmt, $__vartype, $client_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    $resultarray = mysqli_fetch_array($result, MYSQLI_ASSOC);
                } else{
                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                }
            }
            // Close statement
            mysqli_stmt_close($stmt);
            return $resultarray;
        }
        
    function truncate_table($table_name) {
        global $link;
        $resultarray = null;
        $sql = "TRUNCATE TABLE " . $table_name;

        if($stmt = mysqli_prepare($link, $sql)){
            if(mysqli_stmt_execute($stmt)){
                // Success
            } else{
                echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
            }
        }
        // Close statement
        mysqli_stmt_close($stmt);
        return;
    }
    
    function populate_report() {
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
            $url = "https://";   
        else  
            $url = "https://";   
        $url.= $_SERVER['HTTP_HOST'];   
        $url.= $_SERVER['REQUEST_URI'];
        $url_array = explode('/', $url);
        array_pop($url_array);
        $url = implode('/', $url_array);         

        global $link;
        $resultarray = null;
        $sql = "
            INSERT INTO report
        (
        client_id,
        first_name,
        last_name,
        image_url,
        dob,
        age,
        ethnicity_code,
        ethnicity_name,
        required_sessions,
        cause_number,
        referral_type,
        po_first,
        po_last,
        po_office,
        -- paid
        owes,
        -- fee_prob
        -- pay_fail
        attended,
        -- missed
        client_note,
        speaks_sig,
        respect,
        respons_past,
        disrup_arg,
        humor_inap,
        blames,
        drug_alc,
        inapp,
        other_conc,
        behavior_contract_status,
        behavior_contract_signed_date,
        intake_orientation,
        intake_packet,
        birth_place
        )
        SELECT
                c.id client_id,
                c.first_name first_name,
                c.last_name last_name,
                coalesce(concat('" . $url ."/getImageKey.php?id=', c.id, '&key=', i.hash),concat('" . $url ."', '/img/male-placeholder.jpg')),
                c.date_of_birth dob,
                DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth)),'%Y') +0 age,
                e.code ethnicity_code,
                e.name ethnicity_name,
                c.required_sessions required_sessions,
                c.cause_number cause_number,
                rt.referral_type referral_type,
                m.first_name po_first,
                m.last_name po_last,
                m.office po_office,
                -- paid
                client_ledger.balance owes,
                -- fee_prob
                -- pay_fail
                client_attendance.sessions_attended sessions_attended,
                -- missed
                c.note client_note,
                case when speaksSignificantlyInGroup = 0 then 'N' else 'Y' end as speaks_sig,
                case when respectfulTowardsGroup = 0 then 'N' else 'Y' end as respect,
                case when takesResponsibilityForPastBehavior = 0 then 'N' else 'Y' end as respons_past,
                case when disruptiveOrArgumentitive = 1 then 'Y' else 'N' end as disrup_arg,
                case when inappropriateHumor = 1 then 'Y' else 'N' end as humor_inap,
                case when blamesVictim = 1 then 'Y' else 'N' end as blames,
                case when drug_alcohol = 1 then 'Y' else 'N' end as drug_alc,
                case when inappropriate_behavior_to_staff = 1 then 'Y' else 'N' end as inapp,
                c.other_concerns,
                c.behavior_contract_status,
                c.behavior_contract_signed_date,
                c.orientation_date intake_orientation,
                c.intake_packet,
                c.birth_place AS birth_place
                FROM client
                    c
                LEFT OUTER JOIN ethnicity e ON
                    c.ethnicity_id = e.id
                LEFT OUTER JOIN image i ON
                    c.id = i.id
                LEFT OUTER JOIN case_manager m ON
                    c.case_manager_id = m.id
                LEFT OUTER JOIN referral_type rt ON
                    c.referral_type_id = rt.id
                LEFT OUTER JOIN therapy_group tg ON
                    c.therapy_group_id = tg.id
                LEFT OUTER JOIN (select ar.client_id client_id, count(ar.client_id) sessions_attended from attendance_record ar group by ar.client_id) as client_attendance ON
                    c.id = client_attendance.client_id
                LEFT OUTER JOIN (select l.client_id client_id, sum(l.amount) balance from ledger l group by l.client_id) as client_ledger ON
                    c.id = client_ledger.client_id;";

            if($stmt = mysqli_prepare($link, $sql)){
                if(mysqli_stmt_execute($stmt)){
                    // Success
                } else{
                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                }
            }
            // Close statement
            mysqli_stmt_close($stmt);
            return;
        }

        const client_attendance_w_absence_sql = "
        select sub.*,
        CASE WHEN ar_client_id is null THEN 'absent'
             WHEN client_home_group_id = group_id THEN 'present'
             ELSE 'makeup'
             END status
        from
        (SELECT
            ar.client_id ar_client_id,
            c.therapy_group_id client_home_group_id,
            ts.id session_id,
            ts.date session_date,
            tg.id group_id,
            tg.name group_name,
            cur.short_description curriculum
        FROM
            client c
        LEFT join therapy_group tg ON
            c.therapy_group_id = tg.id
        RIGHT JOIN therapy_session ts ON
            tg.id = ts.therapy_group_id and ts.date > c.orientation_date
        LEFT OUTER JOIN curriculum cur ON
            ts.curriculum_id = cur.id
        LEFT OUTER JOIN attendance_record ar ON
            ar.therapy_session_id = ts.id and ar.client_id = c.id
        WHERE
            c.id = ?
        UNION DISTINCT
        -- Select all sessions attended by a particular_client
        SELECT
            ar.client_id client_id,
            c.therapy_group_id client_home_group_id,    
            ts.id session_id,
            ts.date session_date,
            tg.id group_id,
            tg.name group_name,
            cur.short_description curriculum
        FROM
            client c
        LEFT join attendance_record ar ON
            c.id = ar.client_id
        LEFT JOIN therapy_session ts ON
            ar.therapy_session_id = ts.id
        LEFT JOIN therapy_group tg ON
            ts.therapy_group_id = tg.id
        LEFT OUTER JOIN curriculum cur ON
            ts.curriculum_id = cur.id
        WHERE
            ar.client_id = ?
        ORDER BY session_date asc
         ) sub";


         const client_attendance_sql = "
         select sub.*,
         CASE WHEN client_home_group_id = group_id THEN 'present'
              ELSE 'makeup'
              END status
         from
         (
         SELECT
             ar.client_id ar_client_id,
             c.therapy_group_id client_home_group_id,    
             ts.id session_id,
             ts.date session_date,
             tg.id group_id,
             tg.name group_name,
             cur.short_description curriculum
         FROM
             client c
         LEFT join attendance_record ar ON
             c.id = ar.client_id
         LEFT JOIN therapy_session ts ON
             ar.therapy_session_id = ts.id
         LEFT JOIN therapy_group tg ON
             ts.therapy_group_id = tg.id
         LEFT OUTER JOIN curriculum cur ON
             ts.curriculum_id = cur.id
         WHERE
             ar.client_id = ?
         ORDER BY session_date asc
          ) sub";
 
        function get_client_attendance($client_id) {
            global $link;
            $dates = array();
            
            if($stmt = mysqli_prepare($link, client_attendance_w_absence_sql)){
                mysqli_stmt_bind_param($stmt, "ii", $client_id, $client_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if(mysqli_stmt_execute($stmt)){
                        $result = mysqli_stmt_get_result($stmt);
                        while($row = mysqli_fetch_assoc($result)){
                            $dates[] = $row;
                        }
                    } else{
                        echo "ERROR <br>".$stmt->error . "<br>";
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            }
            mysqli_stmt_close($stmt);
            return $dates;
        }


        const client_attendance_summary_sql = "
        SELECT 
        a.client_id client_id,
        a.id record_id,
        IF(excused = '1', 'excused', 'unexcused') status,
        a.date date,
        'group' group_name,
        a.note,
        'curriculum' curriculum
        from absence a
        where a.client_id = ?
        union all
        SELECT
            ar.client_id client_id,
            ts.id session_id,
            'present' status,
            ts.date date,
            tg.name group_name,
            ar.note note,
            cur.short_description curriculum
        FROM
            client c
        LEFT join attendance_record ar ON
            c.id = ar.client_id
        LEFT JOIN therapy_session ts ON
            ar.therapy_session_id = ts.id
        LEFT JOIN therapy_group tg ON
            ts.therapy_group_id = tg.id
        LEFT OUTER JOIN curriculum cur ON
            ts.curriculum_id = cur.id
        WHERE
            ar.client_id = ?        
        order by date asc";

        function get_client_attendance_summary($client_id) {
            global $link;
            $dates = array();
            
            if($stmt = mysqli_prepare($link, client_attendance_summary_sql)){
                mysqli_stmt_bind_param($stmt, "ii", $client_id, $client_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if(mysqli_stmt_execute($stmt)){
                        $result = mysqli_stmt_get_result($stmt);
                        while($row = mysqli_fetch_assoc($result)){
                            $dates[] = $row;
                        }
                    } else{
                        echo "ERROR <br>".$stmt->error . "<br>";
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            }
            mysqli_stmt_close($stmt);
            return $dates;
        }

        const client_attendance_days_sql = "SELECT date(date) session_date FROM attendance_record ar left join therapy_session ts on ar.therapy_session_id = ts.id WHERE client_id = ? ORDER BY session_date ASC";
        function get_client_attendance_days($client_id) {
            global $link;
            $dates = array();

            if($stmt = mysqli_prepare($link, client_attendance_days_sql)){
                mysqli_stmt_bind_param($stmt, "i", $client_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if(mysqli_stmt_execute($stmt)){
                        $result = mysqli_stmt_get_result($stmt);
                        while($row = mysqli_fetch_assoc($result)){
                            $dates[] = $row['session_date'];
                        }
                    } else{
                        echo "ERROR <br>".$stmt->error . "<br>";
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            }
            mysqli_stmt_close($stmt);
            return $dates;
        }

        const client_absence_days_sql = "SELECT date(date) absence_date, excused FROM absence WHERE client_id = ?";
        function get_client_absence_days($client_id) {
            global $link;
            $excused = array();
            $unexcused = array();

            if($stmt = mysqli_prepare($link, client_absence_days_sql)){
                mysqli_stmt_bind_param($stmt, "i", $client_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if(mysqli_stmt_execute($stmt)){
                        $result = mysqli_stmt_get_result($stmt);
                        while($row = mysqli_fetch_assoc($result)){
                            if($row['excused'] == '1') {
                                $excused[] = $row['absence_date'];
                            } else {
                                $unexcused[] = $row['absence_date'];
                            }
                        }
                    } else{
                        echo "ERROR <br>".$stmt->error . "<br>";
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            }
            mysqli_stmt_close($stmt);
            return [$excused, $unexcused];
        }


    function populate_report_client_attendance($client_id) {
        global $link;

        $present_dates = array();
        $absent_dates = array();

        
        if($stmt = mysqli_prepare($link, client_attendance_w_absence_sql)){
            mysqli_stmt_bind_param($stmt, "ii", $client_id, $client_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    while($row = mysqli_fetch_array($result)){
                        if($row["status"] == "absent"){
                            $absent_dates[] = $row["session_date"];
                        }
                        else {
                            $present_dates[] = $row["session_date"];
                        }
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        }
        mysqli_stmt_close($stmt);

        $parameter_array = array();

        $NUM_PRESENT = 27;
        for($count = 0; $count < $NUM_PRESENT; $count++){
            $parameter_array[] = (count($present_dates) > $count) ? $present_dates[$count] : NULL;
        }

        $NUM_ABSENT = 18;
        for($count = 0; $count < $NUM_ABSENT; $count++){
            $parameter_array[] = (count($absent_dates) > $count) ?  $absent_dates[$count] : NULL;
        }
        
        $parameter_array[] = $client_id;

        $sql = "UPDATE report set P1=?, P2=?, P3=?, P4=?, P5=?, P6=?, P7=?, P8=?, P9=?, P10=?, P11=?, P12=?, P13=?, P14=?, P15=?, P16=?, P17=?, P18=?, P19=?, P20=?, P21=?, P22=?, P23=?, P24=?, P25=?, P26=?, P27=?"
        . ", A1=?, A2=?, A3=?, A4=?, A5=?, A6=?, A7=?, A8=?, A9=?, A10=?, A11=?, A12=?, A13=?, A14=?, A15=?, A16=?, A17=?, A18=? WHERE client_id=?";
        $__vartype = "sssssssssssssssssssssssssssssssssssssssssssssi";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
            if(mysqli_stmt_execute($stmt)){
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        } else{
            echo "ERROR <br>".$stmt->error . "<br>";
        }

        mysqli_stmt_close($stmt);
    }
    
?>