select * FROM
(
    select id, first_name, last_name, orientation_date, weekly_attendance,
    (attends_sunday + attends_monday + attends_tuesday + attends_wednesday + attends_thursday + attends_friday + attends_saturday) as sum_attends
    from client
) as client_attends
where weekly_attendance <> sum_attends
order by sum_attends, last_name;


update client
set weekly_attendance = '2'
where
weekly_attendance <> 2
and id in
(
	select id FROM
	(
		select id, first_name, last_name, orientation_date, weekly_attendance,
		(attends_sunday + attends_monday + attends_tuesday + attends_wednesday + attends_thursday + attends_friday + attends_saturday) as sum_attends
		from client
	) as client_attends
	where sum_attends = 2
)