-- Clean up bad quotes in note field
update client set note = CONVERT(note USING ASCII) WHERE note <> CONVERT(note USING ASCII);
UPDATE client set note = REPLACE(note, "???", "'") where note <> REPLACE(note, "???", "'");

-- Clients whith no case manager
SELECT first_name, last_name, p.name, parole_officer, parole_officer_first, parole_officer_last, sheet, tab
from
client c
left JOIN program p on c.program_id = p.id
left JOIN conversion conv on c.id = conv.id
where case_manager_id is null

-- Case managers with their associated client counts
SELECT cm.*, client_count FROM case_manager cm
left outer join (select case_manager_id, count(id) client_count from client group by case_manager_id) client_count_table on case_manager_id = cm.id
order by cm.last_name

-- Attendance days not right
select * from (
		select c.id, first_name, last_name, p.name, orientation_date, weekly_attendance,
		(attends_sunday + attends_sunday_t4c + attends_monday + attends_tuesday + attends_wednesday + attends_thursday + attends_friday + attends_saturday) as sum_attends
		from client c
        left join program p on c.program_id = p.id
    ) attendance_info
where 
weekly_attendance <> sum_attends

-- Find clients missing therapd group
update client c
inner join 
(SELECT c2.id client_id, c2.first_name, c2.last_name, c2.therapy_group_id, conv.sheet, tg.name, tg.id group_id from therapy_group tg, conversion conv, client c2
where c2.id = conv.id and conv.sheet like CONCAT(tg.name ,'%')) as group_ids on c.id = group_ids.client_id
set c.therapy_group_id = group_ids.group_id
where c.therapy_group_id is null

SELECT c2.id client_id, c2.first_name, c2.last_name, c2.therapy_group_id, conv.sheet, tg.name, tg.id group_id from therapy_group tg, conversion conv, client c2
where c2.id = conv.id and conv.sheet like CONCAT(tg.name ,'%') and case_manager_id is null

-- Duplicate therapy sessions
select id, ts.therapy_group_id, ts.date, ts.note from therapy_session ts
join ( SELECT therapy_group_id, date, count(*) FROM `therapy_session` GROUP by therapy_group_id, date HAVING count(*) > 1 ) dups
on ts.therapy_group_id = dups.therapy_group_id and ts.date = dups.date

-- Swap note/other conc
update client
LEFT join conversion on client.id = conversion.id 
set client.other_concerns = conversion.note
where client.id > 1638

-- attendance counts by group in last month
select program.name program, therapy_group.name therapy_group, therapy_session.date session_date, attendance_counts.present
from 
therapy_session
left join therapy_group on therapy_session.therapy_group_id = therapy_group.id
left join program on therapy_group.program_id = program.id
JOIN (select therapy_session_id, count(*) present from attendance_record group by therapy_session_id) attendance_counts on therapy_session.id = attendance_counts.therapy_session_id
WHERE
therapy_session.date >= (CURDATE() - INTERVAL 1 MONTH)
order by program.name, therapy_group.name, therapy_session.date DESC

-- attendance counts by program in last week
select program.name program, sum(attendance_counts.present)
from 
therapy_session
left join therapy_group on therapy_session.therapy_group_id = therapy_group.id
left join program on therapy_group.program_id = program.id
JOIN (select therapy_session_id, count(*) present from attendance_record group by therapy_session_id) attendance_counts on therapy_session.id = attendance_counts.therapy_session_id
WHERE
therapy_session.date >= (CURDATE() - INTERVAL 1 week)
group by program.name
order by program.name, therapy_session.date DESC

-- $$ by program in last week
SELECT p.name, sum(amount) 
FROM ledger
left JOIN client c on ledger.client_id = c.id
left JOIN program p on p.id = c.program_id
WHERE amount > 0 and create_date >= (CURDATE() - INTERVAL 1 WEEK)
GROUP by p.name

-- distinct clients with attendance in last month
select count(*) from 
(select client_id from attendance_record ar
left join therapy_session ts on ar.therapy_session_id = ts.id
where ts.date >= (CURDATE() - INTERVAL 1 MONTH)
GROUP by client_id) as distinct_clients

-- total client sessions in last month
select count(*) from attendance_record ar
left join therapy_session ts on ar.therapy_session_id = ts.id
where ts.date >= (CURDATE() - INTERVAL 1 MONTH)

-- total client attendance by program and week
SELECT ts.date, p.name, count(*)
from attendance_record ar
left JOIN therapy_session ts on ar.therapy_session_id = ts.id
left join therapy_group tg on ts.therapy_group_id = tg.id
left JOIN program p on tg.program_id = p.id
WHERE ts.date > (CURDATE() - INTERVAL 1 MONTH)
group by week(ts.date), p.name
ORDER by ts.date desc

-- Clients with absence and attendance on same day
select c.id, c.first_name, c.last_name
from client c
join absence ab on c.id = ab.client_id
join attendance_record ar on c.id = ar.client_id
join therapy_session ts on ar.therapy_session_id = ts.id
where date(ts.date) = ab.date
order by last_name

-- Clients too many attendance + absence in 1 week
select abse.id, first_name, last_name, present, absent, present+ absent, weekly_attendance
from
client c,
(select c.id, count(client_attendnace_days.date) present
from client c
inner join (select ar.client_id, ts.date from attendance_record ar left join therapy_session ts on ar.therapy_session_id = ts.id) client_attendnace_days on c.id = client_attendnace_days.client_id
where client_attendnace_days.date between "2024-03-31" and "2024-04-06"
group by client_attendnace_days.client_id) as pres,
(select c.id, count(client_absence_days.date) absent
from client c
inner join (select absence.client_id, absence.date from absence) client_absence_days on c.id = client_absence_days.client_id
where date(client_absence_days.date) between "2024-03-31" and "2024-04-06"
group by client_absence_days.client_id) as abse
where pres.id = abse.id and c.id = pres.id
and present+ absent <> weekly_attendance



-- Set stage based on number of sessions attended
update client set client_stage_id = (select id from client_stage where stage = 'Precontemplation');

update client c
inner join 
(select client_id, count(therapy_session_id) cnt from attendance_record group by client_id having cnt > 6) as attend on c.id = attend.client_id
set c.client_stage_id = (select id from client_stage where stage = 'Contemplation')
where program_id <> (select id from program where name like 'Thinking for a Change');

update client c
inner join 
(select client_id, count(therapy_session_id) cnt from attendance_record group by client_id having cnt > 12) as attend on c.id = attend.client_id
set c.client_stage_id = (select id from client_stage where stage = 'Preparation')
where program_id <> (select id from program where name like 'Thinking for a Change');

update client c
inner join 
(select client_id, count(therapy_session_id) cnt from attendance_record group by client_id having cnt > 18) as attend on c.id = attend.client_id
set c.client_stage_id = (select id from client_stage where stage = 'Action')
where program_id <> (select id from program where name like 'Thinking for a Change');

update client c
inner join 
(select client_id, count(therapy_session_id) cnt from attendance_record group by client_id having cnt > 27) as attend on c.id = attend.client_id
set c.client_stage_id = (select id from client_stage where stage = 'Maintenance')
where program_id <> (select id from program where name like 'Thinking for a Change');
