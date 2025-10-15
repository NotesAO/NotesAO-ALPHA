            SELECT
            c.first_name,
            c.last_name,
            c.date_of_birth,
            DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth)),'%Y') +0 age,
            gend.gender,
            e.name ethnicity,
            rt.referral_type referral_type,
            c.phone_number,
            c.email,
            cause_number,
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
            fee fee,
            (select COALESCE(sum(amount),0) from ledger where c.id = ledger.client_id) balance,            
            c.note client_note,
            other_concerns other_concerns
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
            WHERE
                c.id = ?";

CREATE VIEW attendance_view
as
            SELECT
            c.id,
            c.first_name,
            c.last_name,
            c.date_of_birth,
            DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth)),'%Y') +0 age,
            gend.gender,
            e.name ethnicity,
            rt.referral_type referral_type,
            c.phone_number,
            c.email,
            cause_number,
            m.id case_manager_id,
            concat(m.first_name, " ", m.last_name) case_manager,
            m.office po_office,
            g.name group_name,
            orientation_date,
            exit_date,
            er.reason exit_reason,
            exit_note,
            required_sessions sessions_required,
            (select count(*) from attendance_record ar where c.id = ar.client_id) sessions_attended,
            fee fee,
            (select COALESCE(sum(amount),0) from ledger where c.id = ledger.client_id) balance,            
            c.note client_note,
            other_concerns other_concerns,
            ts.date attendance_date,
            ts.duration_minutes
            FROM client
                c
            LEFT OUTER JOIN ethnicity e ON
                c.ethnicity_id = e.id
            LEFT JOIN gender gend ON
                c.gender_id = gend.id
            LEFT OUTER JOIN referral_type rt ON
                c.referral_type_id = rt.id
            LEFT OUTER JOIN exit_reason er ON
                c.exit_reason_id = er.id
            LEFT OUTER JOIN case_manager m ON
                c.case_manager_id = m.id
            LEFT OUTER JOIN therapy_group g ON
                c.therapy_group_id = g.id
            LEFT OUTER JOIN attendance_record ar ON
            	c.id = ar.client_id
            LEFT JOIN therapy_session ts ON
                ar.therapy_session_id = ts.id
                

