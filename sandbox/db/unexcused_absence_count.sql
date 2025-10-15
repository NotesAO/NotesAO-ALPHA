SELECT c.id, first_name, last_name,
COUNT(a.id) unexcused_absence_count
FROM client c
left join absence a
on c.id = a.client_id and a.excused <> 1
GROUP by c.id, first_name, last_name
HAVING unexcused_absence_count > 0