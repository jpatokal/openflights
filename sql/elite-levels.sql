-- Remove elite level from users who have expired
UPDATE users SET elite = ""
  WHERE validity < NOW();

-- Remove warning flag from users who have made their profiles public and have under 100 flights
-- (LEFT JOIN ensures that users with 0 flights are included)
UPDATE users LEFT JOIN (
  SELECT uid
  FROM flights
  GROUP BY uid
  HAVING COUNT(*) < 100
) AS nice_users
ON (users.uid = nice_users.uid)
SET elite = ""
WHERE elite = "X" AND public != 'N' AND users.uid != 1;

-- Set warning flag for non-elite users with >=100 flights
UPDATE users JOIN (
  SELECT uid
  FROM flights
  GROUP BY uid
  HAVING COUNT(*) >= 100
) AS naughty_users
ON (users.uid = naughty_users.uid)
SET elite = "X"
WHERE elite = "" AND users.uid != 1;

-- Set warning flag for non-elite users with hidden profiles
UPDATE users SET elite = "X"
  WHERE elite = "" AND uid != 1 AND public = "N";

-- Summarize
SELECT elite, public, COUNT(*)
  FROM users
  GROUP BY elite, public;

