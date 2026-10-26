<?php
// Automatic Employee / Account IDs in the format YYYY-NNNN (e.g. 2026-0001).
// The counter restarts every year and a number is never handed out twice,
// even after the account that held it is deleted, so login & activity logs
// always keep pointing at the right person.

class AccountId
{
  /**
   * Reserve the next Account ID. Call this only right before inserting the user,
   * every call uses up a number.
   *
   * @param mysqli $conn
   * @return string e.g. "2026-0001"
   */
  public static function generate($conn)
  {
    $year = self::currentYear($conn);

    // Single atomic statement: two people registering at the same moment can
    // never receive the same number
    $stmt = $conn->prepare("INSERT INTO `id_sequences` (`year`, `last_number`) VALUES (?, LAST_INSERT_ID(1))
      ON DUPLICATE KEY UPDATE `last_number` = LAST_INSERT_ID(`last_number` + 1)");
    $stmt->bind_param("i", $year);

    // Skip any number that was already typed in manually before IDs were automated
    do {
      $stmt->execute();
      $number = (int)$conn->query("SELECT LAST_INSERT_ID()")->fetch_row()[0];
      $id = self::format($year, $number);
    } while (self::exists($conn, $id));

    $stmt->close();
    return $id;
  }

  /**
   * The ID the next account will most likely receive. For display only —
   * it does not reserve anything, so never save this value.
   *
   * @param mysqli $conn
   * @return string
   */
  public static function preview($conn)
  {
    $year = self::currentYear($conn);

    $stmt = $conn->prepare("SELECT `last_number` FROM `id_sequences` WHERE `year` = ?");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $number = $row ? (int)$row['last_number'] + 1 : 1;
    while (self::exists($conn, self::format($year, $number))) {
      $number++;
    }
    return self::format($year, $number);
  }

  private static function currentYear($conn)
  {
    // Taken from the database clock (Philippine time). PHP on this server runs on
    // Europe/Berlin, which would still report last year for the first hours of Jan 1.
    return (int)$conn->query("SELECT YEAR(CURDATE())")->fetch_row()[0];
  }

  private static function format($year, $number)
  {
    return sprintf('%d-%04d', $year, $number);
  }

  private static function exists($conn, $id)
  {
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE id_number = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
  }
}
?>
