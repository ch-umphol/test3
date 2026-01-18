-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 09, 2026 at 01:43 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hotel`
--

-- --------------------------------------------------------

--
-- Table structure for table `daily_attendance`
--

CREATE TABLE `daily_attendance` (
  `att_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `work_status` varchar(10) DEFAULT NULL,
  `late_min` int(11) DEFAULT 0,
  `ot_hours` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_attendance`
--

INSERT INTO `daily_attendance` (`att_id`, `emp_id`, `work_date`, `work_status`, `late_min`, `ot_hours`) VALUES
(1, 3, '2025-10-21', '1', 0, 0.00),
(2, 3, '2025-10-22', '1', 0, 0.00),
(3, 3, '2025-10-23', '1', 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `manager_id`) VALUES
(1, 'บริหาร', NULL),
(2, 'ต้อนรับ', NULL),
(3, 'อาหารและเครื่องดื่ม', NULL),
(4, 'แม่บ้าน', NULL),
(5, 'ช่างซ่อมบำรุง', NULL),
(6, 'ครัว', NULL),
(7, 'บัญชี', NULL),
(8, 'บุคคล', NULL),
(9, 'คนสวน', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `emp_id` int(11) NOT NULL,
  `emp_code` varchar(10) NOT NULL,
  `title` varchar(10) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hired_date` date DEFAULT NULL,
  `resign_date` date DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT 4,
  `supervisor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`emp_id`, `emp_code`, `title`, `first_name`, `last_name`, `nickname`, `dept_id`, `position`, `hired_date`, `resign_date`, `username`, `password`, `role_id`, `supervisor_id`) VALUES
(1, '002', 'Mr.', 'ชัยมงคล', 'พรมทา', 'แอดมิน', 1, 'IT Support', '2025-01-01', NULL, 'admin01', '1234', 1, NULL),
(2, '395', 'Mr.', 'Nakornchai', 'Chaiwut', 'ต้น', 3, 'Restaurant Manager', '2025-11-12', NULL, 'manager01', '1234', 2, NULL),
(3, '001', 'Mr.', 'Thanasak', 'Jiraratphisarn', 'โด', 2, 'Resort Supervisor', '2024-05-20', NULL, 'sup01', '1234', 3, NULL),
(4, '394', 'Mr.', 'Naruephon', 'Soda', 'มอส', 2, 'Bellboy', '2025-10-27', NULL, 'emp01', '1234', 4, 3);

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balances`
--

CREATE TABLE `employee_leave_balances` (
  `balance_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `allowed_days` int(11) NOT NULL,
  `used_days` decimal(5,1) DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_leave_balances`
--

INSERT INTO `employee_leave_balances` (`balance_id`, `emp_id`, `leave_type_id`, `year`, `allowed_days`, `used_days`) VALUES
(4, 3, 1, 2025, 30, 0.0),
(5, 3, 2, 2025, 6, 1.0),
(6, 3, 3, 2025, 6, 0.0),
(7, 4, 1, 2025, 30, 1.0),
(8, 4, 2, 2025, 6, 1.0),
(9, 4, 4, 2025, 50, 12.0),
(10, 4, 2, 2026, 6, 2.0),
(11, 4, 3, 2026, 6, 0.0),
(12, 4, 4, 2026, 50, 0.0),
(13, 3, 1, 2026, 30, 3.0),
(14, 3, 2, 2026, 6, 0.0),
(15, 3, 3, 2026, 6, 0.0),
(16, 3, 4, 2026, 50, 0.0),
(17, 4, 1, 2026, 30, 0.0);

-- --------------------------------------------------------

--
-- Table structure for table `holiday_usage_records`
--

CREATE TABLE `holiday_usage_records` (
  `usage_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `holiday_id` int(11) NOT NULL,
  `taken_date` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `request_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type` varchar(10) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `reason` text DEFAULT NULL,
  `evidence_file` varchar(255) DEFAULT NULL,
  `approver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`request_id`, `emp_id`, `leave_type`, `start_date`, `end_date`, `status`, `reason`, `evidence_file`, `approver_id`) VALUES
(4, 3, 'Business L', '2025-12-20', '2025-12-20', 'Approved', 'ไม่สบายพาแฟนไปถ่ายวิดีโอ', 'ev_1766170647_3.jpg', 2),
(5, 4, 'Business L', '2025-12-30', '2025-12-30', 'Approved', 'าืวน่วน่นนงง', 'ev_1766822207_4.jpg', 3),
(6, 4, 'OT', '2025-12-30', '2025-12-30', 'Cancelled', 'มีธุระด่วนต้องจัดการ', NULL, 3),
(7, 4, 'Business L', '2025-12-29', '2025-12-29', 'Cancelled', 'ปวดหัว', NULL, 3),
(8, 4, 'Sick Leave', '2025-12-31', '2025-12-31', 'Approved', 'ไม่สบายยย', NULL, 3),
(9, 4, 'OT', '2025-12-31', '2025-12-31', 'Approved', '[เต็มวัน (8 ชม.)] ลาจ้า', NULL, 3),
(10, 4, 'OT', '2026-01-02', '2026-01-02', 'Cancelled', '[ครึ่งวันเช้า (4 ชม.)] -', NULL, 3),
(11, 4, 'OT', '2026-01-02', '2026-01-02', 'Approved', '[ครึ่งวันเช้า (4 ชม.)] -', NULL, 3),
(12, 4, 'OT', '2026-01-11', '2026-01-11', 'Cancelled', '[ครึ่งวันเช้า (4 ชม.)] dflibfsbjfs;', NULL, 3),
(13, 4, 'OT', '2026-01-11', '2026-01-11', 'Cancelled', '[ครึ่งวันเช้า (4 ชม.)] dxvbffg', NULL, 3),
(19, 3, 'Sick Leave', '2026-01-09', '2026-01-11', 'Approved', 'กอ่กรหอ้ดพริส้รดหย', NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `leave_type_name` varchar(100) NOT NULL,
  `leave_type_display` varchar(100) NOT NULL,
  `max_days` int(11) NOT NULL DEFAULT 0,
  `leave_unit` enum('day','hour') NOT NULL DEFAULT 'day'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `leave_type_display`, `max_days`, `leave_unit`) VALUES
(1, 'Sick Leave', 'ลาป่วย', 30, 'day'),
(2, 'Business Leave', 'ลากิจ', 6, 'day'),
(3, 'Annual Leave', 'ลาพักร้อน', 6, 'day'),
(4, 'OT', 'ลาแบบ OT', 50, 'hour');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_work_summaries`
--

CREATE TABLE `monthly_work_summaries` (
  `summary_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `period_range` varchar(50) DEFAULT NULL,
  `total_sl` int(11) DEFAULT 0,
  `total_bl` int(11) DEFAULT 0,
  `total_al` decimal(5,1) DEFAULT 0.0,
  `total_ex` int(11) DEFAULT 0,
  `total_late_min` int(11) DEFAULT 0,
  `total_ot_hours` decimal(10,2) DEFAULT 0.00,
  `extra_pay` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_holidays`
--

CREATE TABLE `public_holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(255) NOT NULL,
  `leave_type_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `public_holidays`
--

INSERT INTO `public_holidays` (`holiday_id`, `holiday_date`, `holiday_name`, `leave_type_id`) VALUES
(4, '2026-01-01', 'วันขึ้นปีใหม่', NULL),
(5, '2026-01-02', 'วันหยุดพิเศษ (เพิ่มเติม)', NULL),
(6, '2026-03-03', 'วันมาฆบูชา', NULL),
(7, '2026-04-06', 'วันพระบาทสมเด็จพระพุทธยอดฟ้าจุฬาโลกมหาราชและวันที่ระลึกมหาจักรีบรมราชวงศ์', NULL),
(8, '2026-04-13', 'วันสงกรานต์', NULL),
(9, '2026-04-14', 'วันสงกรานต์', NULL),
(10, '2026-04-15', 'วันสงกรานต์', NULL),
(11, '2026-05-04', 'วันฉัตรมงคล', NULL),
(12, '2026-05-09', 'วันพืชมงคล', NULL),
(13, '2026-05-31', 'วันวิสาขบูชา', NULL),
(14, '2026-06-01', 'วันชดเชยวันวิสาขบูชา', NULL),
(15, '2026-06-03', 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าฯ พระบรมราชินี', NULL),
(16, '2026-07-28', 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', NULL),
(17, '2026-07-29', 'วันอาสาฬหบูชา', NULL),
(18, '2026-07-30', 'วันเข้าพรรษา', NULL),
(19, '2026-08-12', 'วันเฉลิมพระชนมพรรษาสมเด็จพระบรมราชชนนีพันปีหลวง และวันแม่แห่งชาติ', NULL),
(20, '2026-10-13', 'วันนวมินทรมหาราช', NULL),
(21, '2026-10-23', 'วันปิยมหาราช', NULL),
(22, '2026-12-05', 'วันพ่อแห่งชาติ', NULL),
(23, '2026-12-07', 'วันชดเชยวันพ่อแห่งชาติ', NULL),
(24, '2026-12-10', 'วันรัฐธรรมนูญ', NULL),
(25, '2026-12-31', 'วันสิ้นปี', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_display` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_display`) VALUES
(1, 'admin', 'ผู้ดูแลระบบ'),
(2, 'manager', 'ผู้จัดการ'),
(3, 'supervisor', 'หัวหน้างาน'),
(4, 'employee', 'พนักงานทั่วไป');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `daily_attendance`
--
ALTER TABLE `daily_attendance`
  ADD PRIMARY KEY (`att_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD KEY `fk_dept_manager` (`manager_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`emp_id`),
  ADD UNIQUE KEY `emp_code` (`emp_code`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `fk_role` (`role_id`),
  ADD KEY `fk_employee_supervisor` (`supervisor_id`);

--
-- Indexes for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `holiday_usage_records`
--
ALTER TABLE `holiday_usage_records`
  ADD PRIMARY KEY (`usage_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `holiday_id` (`holiday_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `monthly_work_summaries`
--
ALTER TABLE `monthly_work_summaries`
  ADD PRIMARY KEY (`summary_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `public_holidays`
--
ALTER TABLE `public_holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`),
  ADD KEY `fk_holiday_leavetype` (`leave_type_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `daily_attendance`
--
ALTER TABLE `daily_attendance`
  MODIFY `att_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `holiday_usage_records`
--
ALTER TABLE `holiday_usage_records`
  MODIFY `usage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `monthly_work_summaries`
--
ALTER TABLE `monthly_work_summaries`
  MODIFY `summary_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_holidays`
--
ALTER TABLE `public_holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daily_attendance`
--
ALTER TABLE `daily_attendance`
  ADD CONSTRAINT `daily_attendance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`emp_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `fk_employee_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `fk_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

--
-- Constraints for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD CONSTRAINT `employee_leave_balances_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `employee_leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`);

--
-- Constraints for table `holiday_usage_records`
--
ALTER TABLE `holiday_usage_records`
  ADD CONSTRAINT `holiday_usage_records_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `holiday_usage_records_ibfk_2` FOREIGN KEY (`holiday_id`) REFERENCES `public_holidays` (`holiday_id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `employees` (`emp_id`);

--
-- Constraints for table `monthly_work_summaries`
--
ALTER TABLE `monthly_work_summaries`
  ADD CONSTRAINT `monthly_work_summaries_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`);

--
-- Constraints for table `public_holidays`
--
ALTER TABLE `public_holidays`
  ADD CONSTRAINT `fk_holiday_leavetype` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
