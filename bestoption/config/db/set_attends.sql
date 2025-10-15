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
ts.date between '2023-08-20' and '2023-08-27'
order by c.id
