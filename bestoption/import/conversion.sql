Add program_id to conversion table
Gender - based on sheet or tab
referral_type_id based on tab ??
PO Office - 
Pays Field
Stage of Change
Other concerns being truncated should be moved to notes
Should we create separate programs for male and female BIPP
Conduct ?  DO we need to convert ?





SET FOREIGN_KEY_CHECKS = 0; 
truncate attendance_record;
truncate absence;
TRUNCATE therapy_session;
TRUNCATE therapy_group;
truncate image;
truncate ledger;
truncate case_manager;
truncate client;
truncate conversion;
SET FOREIGN_KEY_CHECKS = 1;

-- Set program based on sheet name
update conversion set program_id = (select id from program where name like 'BIPP (female)') where sheet = 'Women.BIPP';
update conversion set program_id = (select id from program where name like 'Anger Control') where sheet = 'Anger Control.xlsx';
update conversion set program_id = (select id from program where name like 'BIPP (male)') where program_id is null
 where sheet in
('5th.Street.Saturday.BIPP.xlsx', '5th.Street.Sunday.BIPP.xlsx', 'Lancaster.Saturday.BIPP.xlsx', 'Monday.BIPP.xlsx', 'Monday.Virtual.BIPP.xlsx', 'Tuesday.BIPP.xlsx', 'Sat.Virtual.xlsx', 'Sunday.BIPP.xlsx', 'Wednesday.BIPP.xlsx', 'Lancaster.Saturday.BIPP(2).xlsx');
-- Be sure we covered all the sheets
select distinct sheet from conversion where program_id is null;
select distinct program_id, sheet as name from conversion;

-- Create a default facilitator for all the converted sessions
INSERT INTO facilitator (id, first_name, last_name ) VALUES ('0', 'Conversion', 'Facilitator');

-- Clean up any bad ethnicities
update conversion set ethnicity = 'B' where ethnicity = 'Blk';
update conversion set ethnicity = 'H' where ethnicity = 'H/W';
update conversion set ethnicity = 'O' where ethnicity = 'm,';
update conversion set ethnicity = 'O' where ethnicity = 'I';
update conversion set ethnicity = 'O' where ethnicity = 'C';
update conversion set ethnicity = 'O' where ethnicity = 'Other';
update conversion set ethnicity = 'O' where ethnicity is null;

select ethnicity, count(*) from conversion group by ethnicity;
SELECT distinct sheet, tab, count(*) FROM `conversion` WHERE ethnicity is null group by sheet, tab

-- Parole Officers
select parole_officer, count(*) from conversion group by parole_officer;

update conversion set
parole_officer_first = case when INSTR(parole_officer, ' ') <= 0 then 'X' else SUBSTRING_INDEX(parole_officer, ' ', 1) end;
update conversion set
parole_officer_last = case when INSTR(parole_officer, ' ') <= 0 then parole_officer else SUBSTRING_INDEX(parole_officer, ' ', -1) end;

SELECT parole_officer_last, parole_officer_first, po_office, count(*) from conversion GROUP by parole_officer_last, parole_officer_first, po_office 

update conversion set parole_officer_first = 'Carrie' where parole_officer_last = 'Alba-Ramirez';

-- Clean up conversion table then run the following SQL
INSERT INTO case_manager
(
first_name,
last_name,
office
)
select 
parole_officer_first as first_name,
parole_officer_last  as last_name,
po_office as office
from conversion 
group by parole_officer_first, parole_officer_last, po_office


select 
parole_officer_first as first_name,
parole_officer_last  as last_name,
po_office as po_office
from conversion 
group by parole_officer_first, parole_officer_last

select po_office, count(*) from conversion group by po_office
-- Ask Van

select sheet, count(*) from conversion group by sheet
-- Create the groups, we can go back and change names later
insert into therapy_group (program_id, name) select distinct program_id, sheet as name from conversion;

INSERT INTO client
(
id,
program_id,
first_name,
last_name,
date_of_birth,
-- gender
email,
phone_number,
cause_number,
client_stage_id,
-- referral_type,
ethnicity_id,
required_sessions,
-- fee,
-- case_manager_id,
therapy_group_id,
note,
orientation_date,
other_concerns
-- exit_date,
-- exit_reason,
-- documents_url
-- speaksSignificantlyInGroup,
-- respectfulTowardsGroup,
-- takesResponsibilityForPastBehavior,
-- disruptiveOrArgumentitive,
-- inappropriateHumor,
-- blamesVictim
)
SELECT
        c.id id,
		c.program_id,
        SUBSTRING_INDEX(c.name, ',', -1) first_name,
        SUBSTRING_INDEX(c.name, ',', 1) last_name,
        c.dob date_of_birth,
		c.email,
        c.phone phone_number,
        c.cause_number cause_number,
		1,
        e.id ethnicity_id,
        c.`18_27_wks` required_sessions,
		tg.id therapy_group_id,
		c.note note,
        c.intake_orientation orientation_date,
		c.other_conc
--        case when speaksSignificantlyInGroup = 0 then 'No' else 'Yes' end as speaks_sig,
        FROM Conversion C
        LEFT OUTER JOIN ethnicity e ON
            c.ethnicity = e.code
        LEFT OUTER JOIN therapy_group tg ON
            c.sheet = tg.name
			

-- set referal type based on tab name
select tab, count(*) from conversion GROUP BY tab;
update client SET referral_type_id = (select id from referral_type where referral_type = 'Probation' ) where id in (select id from conversion where lower(tab) like '%probation%');
update client SET referral_type_id = (select id from referral_type where referral_type = 'Parole' ) where id in (select id from conversion where lower(tab) like '%parole%');
update client SET referral_type_id = (select id from referral_type where referral_type = 'Pretrial' ) where id in (select id from conversion where lower(tab) like '%court%');
update client SET referral_type_id = (select id from referral_type where referral_type = 'Probation' ) where id in (select id from conversion where lower(po_office) like '%probation%');
update client SET referral_type_id = (select id from referral_type where referral_type = 'Parole' ) where id in (select id from conversion where lower(po_office) like '%parole%');

-- set gender based on group, sheet, or tab?
update client set gender_id = (select id from gender where gender = 'male' ) where program_id = (select id from program where name = 'BIPP (male)');
update client set gender_id = (select id from gender where gender = 'female' ) where program_id = (select id from program where name = 'BIPP (female)');
update client set gender_id = (select id from gender where gender = 'female' ) where program_id = 2 and id in (select id from conversion where sheet like '%Women%');
update client set gender_id = (select id from gender where gender = 'female' ) where program_id = 2 and id in (select id from conversion where tab like '%Fem%');

-- set case manager
update client set case_manager_id = (select cm.id from case_manager cm, conversion c where cm.first_name = c.parole_officer_first and cm.last_name=parole_officer_last and c.id = client.id) where case_manager_id is null;

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

-- set attends day based on recent attendance
update client set weekly_attendance = 1 where program_id in (select id from program where name like 'BIPP%');

SELECT
concat(
'update client ',
case
when dayofweek(ts.date) = 1 then 'set attends_sunday=\'1\''
when dayofweek(ts.date) = 2 then 'set attends_monday=\'1\''
when dayofweek(ts.date) = 3 then 'set attends_tuesday=\'1\''
when dayofweek(ts.date) = 4 then 'set attends_wednesday=\'1\''
when dayofweek(ts.date) = 5 then 'set attends_thursday=\'1\''
when dayofweek(ts.date) = 6 then 'set attends_friday=\'1\''
when dayofweek(ts.date) = 7 then 'set attends_saturday=\'1\'' end,
' where id= ',
c.id,
';')
FROM client c
left join attendance_record ar ON
c.id = ar.client_id
left join therapy_session ts on
ar.therapy_session_id = ts.id
where 
ts.date between '2024-01-04' and '2024-02-19'
and program_id in (2, 3, 4)
order by c.id
