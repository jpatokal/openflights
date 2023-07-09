SELECT apid, name, iata, airports.icao FROM airports
INNER JOIN (
    SELECT icao FROM airports
    GROUP BY icao HAVING count(icao) > 1
) dup ON airports.icao = dup.icao
WHERE airports.icao != ""
ORDER BY icao;

