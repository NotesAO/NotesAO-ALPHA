import java.io.File;
import java.io.FileInputStream;
import java.io.FilenameFilter;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.text.DateFormat;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.time.LocalDate;
import java.time.ZoneOffset;
import java.util.HashMap;
import java.util.Iterator;

import org.apache.poi.ss.usermodel.Cell;
import org.apache.poi.ss.usermodel.CellType;
import org.apache.poi.ss.usermodel.Row;
import org.apache.poi.xssf.usermodel.XSSFSheet;
import org.apache.poi.xssf.usermodel.XSSFWorkbook;

public class bippImportAddicare {
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
        String jdbc_insert_sql = "INSERT INTO conversion_addicare "
                + "(var, name, id_num, id_num_type, dob, ethnicity, victim_rel, victim_gender, referral_date, referral_source, orientation_date, exit_date, exit_reason, intake_hours, orientation_hours, group_hours, individual_hours, note) VALUES (";
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
                                colNameMap.get("variable") == null ||
                                getValue(row, "variable") == null) {
                            // Skip blank rows
                            isDone = true;
                        } else {
                            try {
                                int index = 1;
                                stmt.setObject(index++, getValue(row, "Variable"));
                                stmt.setObject(index++, getValue(row, "Name"));
                                stmt.setObject(index++, getValue(row, "id_num"));
                                stmt.setObject(index++, getValue(row, "id_num_type"));
                                stmt.setObject(index++, asDate(getValue(row, "DOB")));
                                stmt.setObject(index++, getValue(row, "Ethnicity"));
                                stmt.setObject(index++, getValue(row, "victim_rel"));
                                stmt.setObject(index++, getValue(row, "victim_gender"));
                                stmt.setObject(index++, asDate(getValue(row, "referral_date")));
                                stmt.setObject(index++, getValue(row, "referral_source"));
                                stmt.setObject(index++, asDate(getValue(row, "orientation_date")));
                                stmt.setObject(index++, asDate(getValue(row, "exit_date")));
                                stmt.setObject(index++, getValue(row, "exit_reason"));
                                stmt.setObject(index++, getValue(row, "intake_hours"));
                                stmt.setObject(index++, getValue(row, "orientation_hours"));
                                stmt.setObject(index++, getValue(row, "group_hours"));
                                stmt.setObject(index++, getValue(row, "individual_hours"));
                                stmt.setObject(index++, getValue(row, "note"));

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
                } else if (s.equalsIgnoreCase("no") || s.equalsIgnoreCase("paid in full")) {
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
            DateFormat dateParser = new SimpleDateFormat("M/d/yy");
            return dateParser.parse((String) value);
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
        if (columnIndex == null) {
            System.out.println("Unknown key: " + key + " in workbook: " + filename + " worksheet " + worksheetName);
            // throw new MissingKeyException(
            // "Unknown key: " + key + " in workbook: " + filename + " worksheet " +
            // worksheetName);
            returnValue = null;
        } else {
            Cell cell = row.getCell(columnIndex.intValue());
            if (cell != null) {
                switch (cell.getCellType()) {
                    case STRING:
                        returnValue = cell.getStringCellValue();
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
