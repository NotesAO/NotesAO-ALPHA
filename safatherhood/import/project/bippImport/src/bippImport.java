import java.io.*;
import org.apache.poi.xssf.usermodel.XSSFWorkbook;
import org.apache.poi.ss.usermodel.Cell;
import org.apache.poi.ss.usermodel.CellType;
import org.apache.poi.ss.usermodel.Row;
import org.apache.poi.xssf.usermodel.XSSFSheet;
import java.util.*;
import java.sql.*;
import java.text.DateFormat;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.time.LocalDate;
import java.time.ZoneOffset;

public class bippImport {
    public static String folderName = "C:/therapytrack/import/datafiles";
    public static String dbURL = "jdbc:mysql://localhost:3306/therapy_track";
    public static String dbUser = "root";
    public static String dbPass = null;

    public static String worksheetName = null;
    public static String filename = null;
    public static HashMap<String, Integer> colNameMap = new HashMap<>(50);

    public static void main(String[] args) throws Exception {
        Connection conn = DriverManager.getConnection(dbURL, dbUser, dbPass);

        PreparedStatement stmt = null;
        String jdbc_insert_sql = "INSERT INTO conversion "
                + "(sheet, tab, date, name, dob, age, ethnicity, 18_27_wks, parole_officer, PO_office, paid, owes, fee_prob, pay_fail, attended, missed, note, phone, speaks_sig, respect, respons_past, disrup_arg, humor_inap, blames, drug_alc, inapp, other_conc, intake_orientation, P1, P2, P3, P4, P5, P6, P7, P8, P9, P10, P11, P12, P13, P14, P15, P16, P17, P18, P19, P20, P21, P22, P23, P24, P25, P26, P27, A1, A2, A3, A4, A5, A6, A7, A8, A9, A10, A11, A12, A13, A14, A15, A16, A17, A18, email) VALUES (";
        String[] tokens = jdbc_insert_sql.split(",");
        for (int i = 0; i < tokens.length; i++) {
            jdbc_insert_sql = jdbc_insert_sql + (i == 0 ? "?" : ",?");
        }
        jdbc_insert_sql = jdbc_insert_sql + ");";
        stmt = conn.prepareStatement(jdbc_insert_sql);

        File folder = new File(folderName);
        File[] files = folder.listFiles(new FilenameFilter() {
            public boolean accept(File dir, String name) {
                return name.toLowerCase().endsWith(".xlsx");
            }
        });

        for (int fIndex = 0; fIndex < files.length; fIndex++) {
            File dataFile = files[fIndex];
            filename = dataFile.getName();

            FileInputStream input_document = new FileInputStream(dataFile);
            XSSFWorkbook workbook = new XSSFWorkbook(input_document);

            // For each Worksheet
            for (int i = 0; i < workbook.getNumberOfSheets(); i++) {
                XSSFSheet worksheet = workbook.getSheetAt(i);
                worksheetName = worksheet.getSheetName();

                // Skip exited to avoid dirty data
                if (worksheetName == null
                        || worksheetName.startsWith("Graduates")
                        || worksheetName.startsWith("Grads")
                        || worksheetName.startsWith("DCd")
                        || worksheetName.startsWith("Exit")
                        || worksheetName.startsWith("EXIT")
                        || worksheetName.startsWith("Completions")
                        || worksheetName.startsWith("Unsuccessful")
                        || worksheetName.startsWith("Successful")) {
                    continue;
                }

                boolean isDone = false;

                if (worksheet.getPhysicalNumberOfRows() < 2) {
                    System.out.println(filename + " skipping sheet " + worksheetName + " because it is empty");
                    continue;
                }

                // For each Row
                int rowNum = 0;
                Iterator<Row> rowIterator = worksheet.iterator();
                while (rowIterator.hasNext() && !isDone) {
                    rowNum++;
                    Row row = rowIterator.next();
                    if (rowNum == 1) {
                        // Load the column Names
                        colNameMap = new HashMap<>(50);
                        Iterator<Cell> cellIterator = row.cellIterator();
                        int colNum = 0;
                        while (cellIterator.hasNext()) {
                            Cell cell = cellIterator.next();
                            colNameMap.put(cleanKey(cell.getStringCellValue()), colNum);
                            colNum++;
                        }
                    } else {
                        if (row.getCell(0) == null ||
                                colNameMap.get("date") == null ||
                                getValue(row, "date") == null) {
                            // Skip blank rows
                            isDone = true;
                        } else {
                            try {
                                int index = 1;
                                stmt.setObject(index++, filename);
                                stmt.setObject(index++, worksheetName);
                                stmt.setObject(index++, asDate(getValue(row, "Date")));
                                stmt.setObject(index++, getValue(row, "Name"));
                                stmt.setObject(index++, asDate(getValue(row, "DOB")));
                                stmt.setObject(index++, getValue(row, "Age"));
                                stmt.setObject(index++, getValue(row, "Ethnicity"));
                                stmt.setObject(index++, getValue(row, "18 27 wks"));
                                // stmt.setObject(index++, getValue(row, "Cause Number"));
                                stmt.setObject(index++, getValue(row, "Parole Officer"));
                                stmt.setObject(index++, getValue(row, "PO Office"));
                                stmt.setObject(index++, getValue(row, "Paid"));
                                stmt.setObject(index++, getOwes(getValue(row, "Owes")));
                                stmt.setObject(index++, getValue(row, "Fee Prob"));
                                stmt.setObject(index++, getValue(row, "pay fail"));
                                stmt.setObject(index++, getValue(row, "attended"));
                                stmt.setObject(index++, getValue(row, "missed"));
                                stmt.setObject(index++, getValue(row, "other conc"));
                                stmt.setObject(index++, getValue(row, "phone"));
                                stmt.setObject(index++, getValue(row, "speaks sig m"));
                                stmt.setObject(index++, getValue(row, "respect y"));
                                stmt.setObject(index++, getValue(row, "responspast y"));
                                stmt.setObject(index++, getValue(row, "disrupargu y"));
                                stmt.setObject(index++, getValue(row, "humorinap y"));
                                stmt.setObject(index++, getValue(row, "blames y"));
                                stmt.setObject(index++, getValue(row, "drug alc y"));
                                stmt.setObject(index++, getValue(row, "inapp y"));
                                stmt.setObject(index++, getValue(row, "note"));
                                stmt.setObject(index++, asDate(getValue(row, "intake orientation")));
                                stmt.setObject(index++, asDate(getValue(row, "P1")));
                                stmt.setObject(index++, asDate(getValue(row, "P2")));
                                stmt.setObject(index++, asDate(getValue(row, "P3")));
                                stmt.setObject(index++, asDate(getValue(row, "P4")));
                                stmt.setObject(index++, asDate(getValue(row, "P5")));
                                stmt.setObject(index++, asDate(getValue(row, "P6")));
                                stmt.setObject(index++, asDate(getValue(row, "P7")));
                                stmt.setObject(index++, asDate(getValue(row, "P8")));
                                stmt.setObject(index++, asDate(getValue(row, "P9")));
                                stmt.setObject(index++, asDate(getValue(row, "P10")));
                                stmt.setObject(index++, asDate(getValue(row, "P11")));
                                stmt.setObject(index++, asDate(getValue(row, "P12")));
                                stmt.setObject(index++, asDate(getValue(row, "P13")));
                                stmt.setObject(index++, asDate(getValue(row, "P14")));
                                stmt.setObject(index++, asDate(getValue(row, "P15")));
                                stmt.setObject(index++, asDate(getValue(row, "P16")));
                                stmt.setObject(index++, asDate(getValue(row, "P17")));
                                stmt.setObject(index++, asDate(getValue(row, "P18")));
                                stmt.setObject(index++, asDate(getValue(row, "P19")));
                                stmt.setObject(index++, asDate(getValue(row, "P20")));
                                stmt.setObject(index++, asDate(getValue(row, "P21")));
                                stmt.setObject(index++, asDate(getValue(row, "P22")));
                                stmt.setObject(index++, asDate(getValue(row, "P23")));
                                stmt.setObject(index++, asDate(getValue(row, "P24")));
                                stmt.setObject(index++, asDate(getValue(row, "P25")));
                                stmt.setObject(index++, asDate(getValue(row, "P26")));
                                stmt.setObject(index++, asDate(getValue(row, "P27")));
                                stmt.setObject(index++, asDate(getValue(row, "A1")));
                                stmt.setObject(index++, asDate(getValue(row, "A2")));
                                stmt.setObject(index++, asDate(getValue(row, "A3")));
                                stmt.setObject(index++, asDate(getValue(row, "A4")));
                                stmt.setObject(index++, asDate(getValue(row, "A5")));
                                stmt.setObject(index++, asDate(getValue(row, "A6")));
                                stmt.setObject(index++, asDate(getValue(row, "A7")));
                                stmt.setObject(index++, asDate(getValue(row, "A8")));
                                stmt.setObject(index++, asDate(getValue(row, "A9")));
                                stmt.setObject(index++, asDate(getValue(row, "A10")));
                                stmt.setObject(index++, asDate(getValue(row, "A11")));
                                stmt.setObject(index++, asDate(getValue(row, "A12")));
                                stmt.setObject(index++, asDate(getValue(row, "A13")));
                                stmt.setObject(index++, asDate(getValue(row, "A14")));
                                stmt.setObject(index++, asDate(getValue(row, "A15")));
                                stmt.setObject(index++, asDate(getValue(row, "A16")));
                                stmt.setObject(index++, asDate(getValue(row, "A17")));
                                stmt.setObject(index++, asDate(getValue(row, "A18")));
                                stmt.setObject(index++, getValue(row, "email"));
                                stmt.executeUpdate();
                            } catch (MissingKeyException ex) {
                                System.out.println(filename + " sheet " + worksheetName + " row " + rowNum + " error "
                                        + ex.getMessage());
                                isDone = true; // If the sheet is missing a column there is no point in continuing
                            } catch (Exception ex) {
                                System.out.println(filename + " sheet " + worksheetName + " row " + rowNum + " error "
                                        + ex.getMessage());
                                ex.printStackTrace(System.err);
                            }
                        }
                    }
                }
                System.out.println(filename + " sheet " + worksheetName + " rows " + rowNum);
            }

            workbook.close();
            input_document.close();
        }

        stmt.close();
        conn.close();
    }

    private static String maxLength(String str, int length) {
        if (str == null || str.length() < length) {
            return str;
        }
        return str.substring(0, length - 1);
    }

    private static Object getOwes(Object value) {
        if (value == null) {
            // OK
        } else if (value instanceof Number) {
            // A OK
        } else if (value instanceof String) {
            String s = (String) value;
            String temp = s;
            // Handle some one off cases
            if (!isNumeric(s)) {
                // Handle the Neg, Pos cases
                s = s.toUpperCase().trim();
                if (s.startsWith("POS")) {
                    s = s.substring(3).trim();
                    if (isNumeric(s)) {
                        value = Double.parseDouble(s);
                    }
                } else if (s.startsWith("NEG")) {
                    s = s.substring(3).trim();
                    if (isNumeric(s)) {
                        value = Double.parseDouble(s) * -1.0;
                    }
                } else if (s.isEmpty() || s.equalsIgnoreCase("no") || s.equalsIgnoreCase("paid in full")
                        || s.equalsIgnoreCase("paid full")) {
                    value = Integer.valueOf(0);
                }
                System.out.println("Owes - Bad Number: " + temp + " converted to " + value);
            }
        }
        return value;
    }

    public static boolean isNumeric(String str) {
        try {
            Double.parseDouble(str);
            return true;
        } catch (NumberFormatException e) {
            return false;
        }
    }

    // A little help making the column names consistent
    private static String cleanKey(String key) {
        if (key != null) {
            key = key.toLowerCase().replaceAll("\\s", "");
            if (key.startsWith("note")) {
                key = "note";
            }
            if (key.startsWith("eth")) {
                key = "ethnicity";
            }
            if (key.startsWith("phone")) {
                key = "phone";
            }
            if (key.startsWith("18")) {
                key = "18 27 wks";
            }
        }
        return key;
    }

    private static java.util.Date asDate(Object value) throws ParseException {

        if (value == null) {
            return null;
        }
        if (value instanceof String) {
            String temp = (String) value;
            temp = temp.trim();
            if (!temp.isEmpty()) {
                try {
                    DateFormat dateParser = new SimpleDateFormat("M/d/yy");
                    return dateParser.parse((String) temp);
                } catch (Exception e) {
                    System.out.println("Ignoring Unparseable Date String:" + temp);
                    // e.printStackTrace();
                }
            }
        }
        if (value instanceof Double) {
            // Convert between Jan 1 1900 (excel) and Jan 1 1970 (java)
            LocalDate d = LocalDate.ofEpochDay(((Double) value).longValue() - 25568);
            return java.util.Date.from(d.atStartOfDay().toInstant(ZoneOffset.UTC));
        }
        return null;
    }

    private static Object getValue(Row row, String key) throws MissingKeyException {
        key = cleanKey(key);
        Object returnValue = null;
        Integer columnIndex = colNameMap.get(key.toLowerCase());
        if (columnIndex == null && key.toLowerCase().equals("")) {
            columnIndex = colNameMap.get(key.toLowerCase());
        }
        if (columnIndex == null) {
            if (!key.equalsIgnoreCase("phone")) {
                System.out.println("Unknown key: " + key + " in workbook: " + filename + " worksheet " + worksheetName);
            }
            // throw new MissingKeyException(
            // "Unknown key: " + key + " in workbook: " + filename + " worksheet " +
            // worksheetName);
            returnValue = null;
        } else {
            Cell cell = row.getCell(columnIndex.intValue());
            if (cell != null) {
                switch (cell.getCellType()) {
                    case STRING:
                        returnValue = cell.getStringCellValue().trim();
                        break;
                    case NUMERIC:
                        returnValue = Double.valueOf(cell.getNumericCellValue());
                        break;
                    case BOOLEAN:
                        returnValue = Boolean.valueOf(cell.getBooleanCellValue());
                        break;
                    case FORMULA:
                        if (cell.getCachedFormulaResultType() == CellType.STRING)
                            returnValue = cell.getStringCellValue();
                        else if (cell.getCachedFormulaResultType() == CellType.NUMERIC)
                            returnValue = cell.getNumericCellValue();
                        else
                            returnValue = null;
                        break;
                    case BLANK, _NONE, ERROR:
                        returnValue = null;
                        break;
                }
            }
        }
        if (returnValue instanceof String) {
            returnValue = ((String) returnValue).trim();
        }
        return returnValue;
    }
}
