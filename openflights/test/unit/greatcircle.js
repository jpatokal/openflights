parseTimeStringTest = TestCase("parseTimeStringTest");

parseTimeStringTest.prototype.testParseTimeString = function() {
  assertEquals(6.5, parseTimeString("06:30"));
  assertEquals(6.5, parseTimeString("6:30"));
  assertEquals(6.5, parseTimeString("0630"));
  assertEquals(6.5, parseTimeString("630"));
};
