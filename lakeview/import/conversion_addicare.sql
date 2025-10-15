
SET FOREIGN_KEY_CHECKS = 0; 
truncate attendance_record;
TRUNCATE therapy_session;
TRUNCATE therapy_group;
TRUNCATE client;
truncate image;
truncate ledger;
truncate case_manager;
truncate conversion;
SET FOREIGN_KEY_CHECKS = 1;


select ethnicity, count(*) from conversion_addicare group by ethnicity;
-- Confirm and add manually
update conversion_addicare set ethnicity='B' where ethnicity='Black';
update conversion_addicare set ethnicity='W' where ethnicity='Caucasian';
update conversion_addicare set ethnicity='H' where ethnicity='Hispanic';
update conversion_addicare set ethnicity='O' where ethnicity='Other';

update conversion_addicare set exit_reason='Violation of Requirements' where exit_reason='Terminated from Program';

INSERT INTO client
(
id,
first_name,
last_name,
date_of_birth,
ethnicity_id,
referral_type_id,
note,
orientation_date,
exit_date,
exit_reason_id
)
SELECT
        c.id id,
        SUBSTRING_INDEX(c.name, ' ', 1) first_name,
        SUBSTRING_INDEX(c.name, ' ', -1) last_name,
        c.dob date_of_birth,
        e.id ethnicity_id,
		rt.id,
		c.note note,
        c.orientation_date orientation_date,
        c.exit_date exit_date,
		er.id
        FROM Conversion_addicare C
        LEFT OUTER JOIN ethnicity e ON
            c.ethnicity = e.code
        LEFT OUTER JOIN referral_type rt ON
            c.referral_source = rt.referral_type
        LEFT OUTER JOIN exit_reason er ON
            c.exit_reason = er.reason

-- set gender based on group ?
-- set referal type based on tab name
select tab, count(*) from conversion GROUP BY tab;
update client
SET referral_type_id = (select id from referral_type where referral_type = 'Probation' )
where id in (select id from conversion where lower(tab) like '%probation%');
update client
SET referral_type_id = (select id from referral_type where referral_type = 'Parole' )
where id in (select id from conversion where lower(tab) like '%parole%');


-- set case manager
update client set case_manager_id = (select cm.id from case_manager cm, conversion c where cm.first_name = c.parole_officer_first and cm.last_name=parole_officer_last and c.id = client.id)


insert into therapy_session
(
    therapy_group_id,
    date,
    duration_minutes,
    facilitator_id
)
select tg.id therapy_group_id, session_date date, 120 duration_minutes, 0 facilitator_id
from
(
SELECT distinct sheet, P1 session_date from conversion where p1 is not null
union SELECT sheet, P2 session_date from conversion where p2 is not null
union SELECT sheet, P3 session_date from conversion where p3 is not null
union SELECT sheet, P4 session_date from conversion where p4 is not null
union SELECT sheet, P5 session_date from conversion where p5 is not null
union SELECT sheet, P6 session_date from conversion where p6 is not null
union SELECT sheet, P7 session_date from conversion where p7 is not null
union SELECT sheet, P8 session_date from conversion where p8 is not null
union SELECT sheet, P9 session_date from conversion where p9 is not null
union SELECT sheet, P10 session_date from conversion where p10 is not null
union SELECT sheet, P11 session_date from conversion where p11 is not null
union SELECT sheet, P12 session_date from conversion where p12 is not null
union SELECT sheet, P13 session_date from conversion where p13 is not null
union SELECT sheet, P14 session_date from conversion where p14 is not null
union SELECT sheet, P15 session_date from conversion where p15 is not null
union SELECT sheet, P16 session_date from conversion where p16 is not null
union SELECT sheet, P17 session_date from conversion where p17 is not null
union SELECT sheet, P18 session_date from conversion where p18 is not null
union SELECT sheet, P19 session_date from conversion where p19 is not null
union SELECT sheet, P20 session_date from conversion where p20 is not null
union SELECT sheet, P21 session_date from conversion where p21 is not null
union SELECT sheet, P22 session_date from conversion where p22 is not null
union SELECT sheet, P23 session_date from conversion where p23 is not null
union SELECT sheet, P24 session_date from conversion where p24 is not null
union SELECT sheet, P25 session_date from conversion where p25 is not null
union SELECT sheet, P26 session_date from conversion where p26 is not null
union SELECT sheet, P27 session_date from conversion where p27 is not null
order by sheet, session_date
) sessions
left join therapy_group tg on tg.name = sessions.sheet

insert into attendance_record
(client_id,therapy_session_id)
select client_id, ts.id
FROM
(
select sessions.id client_id, tg.id therapy_group_id, session_date session_date
from
(
SELECT distinct sheet, id, P1 session_date from conversion where p1 is not null
union SELECT sheet, id, P2 session_date from conversion where p2 is not null
union SELECT sheet, id, P3 session_date from conversion where p3 is not null
union SELECT sheet, id, P4 session_date from conversion where p4 is not null
union SELECT sheet, id, P5 session_date from conversion where p5 is not null
union SELECT sheet, id, P6 session_date from conversion where p6 is not null
union SELECT sheet, id, P7 session_date from conversion where p7 is not null
union SELECT sheet, id, P8 session_date from conversion where p8 is not null
union SELECT sheet, id, P9 session_date from conversion where p9 is not null
union SELECT sheet, id, P10 session_date from conversion where p10 is not null
union SELECT sheet, id, P11 session_date from conversion where p11 is not null
union SELECT sheet, id, P12 session_date from conversion where p12 is not null
union SELECT sheet, id, P13 session_date from conversion where p13 is not null
union SELECT sheet, id, P14 session_date from conversion where p14 is not null
union SELECT sheet, id, P15 session_date from conversion where p15 is not null
union SELECT sheet, id, P16 session_date from conversion where p16 is not null
union SELECT sheet, id, P17 session_date from conversion where p17 is not null
union SELECT sheet, id, P18 session_date from conversion where p18 is not null
union SELECT sheet, id, P19 session_date from conversion where p19 is not null
union SELECT sheet, id, P20 session_date from conversion where p20 is not null
union SELECT sheet, id, P21 session_date from conversion where p21 is not null
union SELECT sheet, id, P22 session_date from conversion where p22 is not null
union SELECT sheet, id, P23 session_date from conversion where p23 is not null
union SELECT sheet, id, P24 session_date from conversion where p24 is not null
union SELECT sheet, id, P25 session_date from conversion where p25 is not null
union SELECT sheet, id, P26 session_date from conversion where p26 is not null
union SELECT sheet, id, P27 session_date from conversion where p27 is not null
order by sheet, id, session_date
) sessions
left join therapy_group tg on tg.name = sessions.sheet
) sessions2
left join therapy_session ts on ts.date = sessions2.session_date and ts.therapy_group_id = sessions2.therapy_group_id

-- completions
update client as c 
inner join conversion on c.id = conversion.id
SET
c.exit_date = conversion.date
, c.exit_reason_id = (select id from exit_reason where reason like 'Violation%' )
where 
lower(conversion.tab) like '%exit%'

update client as c 
inner join conversion on c.id = conversion.id
SET
c.exit_date = conversion.date
, c.exit_reason_id = (select id from exit_reason where reason like 'Completion%' )
where 
lower(conversion.tab) like '%complete%' or lower(conversion.tab) like '%grad%' or lower(conversion.tab) like '%success%'

select tab, count(*) from conversion GROUP BY tab;
update client as c 
inner join 
(
    	select id, first_name, last_name, max(attendance_date) last_attendance
    	from attendance_view where sessions_attended >= sessions_required group by id, first_name, last_name
) as exits 
on c.id = exits.id
SET
c.exit_date = exits.last_attendance
, c.exit_reason_id = (select id from exit_reason where reason like 'Completion%' )