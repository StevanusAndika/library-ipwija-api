-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 14, 2026 at 10:31 AM
-- Server version: 8.0.30
-- PHP Version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `library_ipwija`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateAndUpdateLateStatus` (IN `p_borrowing_id` INT)   BEGIN
    DECLARE v_due_date DATE;
    DECLARE v_returned_at DATETIME;
    DECLARE v_status VARCHAR(20);
    DECLARE v_late_days INT DEFAULT 0;
    DECLARE v_fine_amount DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_fine_per_day DECIMAL(10,2) DEFAULT 1000.00; -- Rp 1.000/hari
    DECLARE v_current_due_date DATE;
    
    -- Ambil data peminjaman
    SELECT 
        COALESCE(extended_due_date, due_date) AS current_due_date,
        returned_at, 
        status
    INTO v_current_due_date, v_returned_at, v_status
    FROM borrowings 
    WHERE id = p_borrowing_id;
    
    -- Hitung hari terlambat
    IF v_returned_at IS NOT NULL THEN
        -- Jika sudah dikembalikan, hitung berdasarkan return_date
        IF DATE(v_returned_at) > v_current_due_date THEN
            SET v_late_days = DATEDIFF(DATE(v_returned_at), v_current_due_date);
        END IF;
    ELSE
        -- Jika belum dikembalikan, hitung berdasarkan hari ini
        IF CURDATE() > v_current_due_date THEN
            SET v_late_days = DATEDIFF(CURDATE(), v_current_due_date);
        END IF;
    END IF;
    
    -- Hitung denda
    SET v_fine_amount = v_late_days * v_fine_per_day;
    
    -- Update ke database
    UPDATE borrowings 
    SET 
        late_days = v_late_days,
        fine_amount = v_fine_amount,
        -- Update status jika terlambat dan masih dipinjam
        status = CASE 
                    WHEN v_returned_at IS NULL AND v_late_days > 0 
                    THEN 'overdue' 
                    ELSE status 
                 END,
        updated_at = NOW()
    WHERE id = p_borrowing_id;
    
    -- Tampilkan hasil
    SELECT 
        p_borrowing_id AS borrowing_id,
        v_current_due_date AS due_date,
        v_returned_at AS returned_date,
        v_late_days AS days_late,
        v_fine_amount AS total_fine,
        CASE 
            WHEN v_late_days > 0 AND v_returned_at IS NOT NULL 
            THEN 'Late (returned)'
            WHEN v_late_days > 0 AND v_returned_at IS NULL 
            THEN 'Late (still borrowed)'
            ELSE 'Not late'
        END AS status_description;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` bigint UNSIGNED NOT NULL,
  `isbn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint UNSIGNED NOT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publisher` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publication_year` year NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `available_stock` int NOT NULL DEFAULT '0',
  `book_type` enum('hardcopy','softcopy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hardcopy',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `synopsis` text COLLATE utf8mb4_unicode_ci,
  `pages` int DEFAULT NULL,
  `language` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Indonesia',
  `status` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `isbn`, `title`, `slug`, `category_id`, `author`, `publisher`, `publication_year`, `stock`, `available_stock`, `book_type`, `file_path`, `cover_image`, `description`, `synopsis`, `pages`, `language`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '123456789', 'Juki Si Kecil |  Petualangan si juki', 'juki-si-kecil-petualangan-si-juki', 9, 'Faza Ibnu Ubaidillah Salman', 'webcomic', '2020', 100, 99, 'hardcopy', NULL, 'books/covers/VlEfa1XfHdTqLgunNxKbUhkBtEMzzMAgMA0Lka4L.png', NULL, NULL, NULL, 'Indonesia', 1, '2026-01-14 01:14:25', '2026-01-14 02:00:55', NULL),
(5, '124010340501', 'Petualangan doraemon', 'petualangan-doraemon', 9, 'Fujiko F.Fujio', 'Elex media computindo', '2010', 100, 100, 'hardcopy', NULL, 'books/covers/TSsDrdaghXoUkGDK5XfWUmRUmH2g0ke0U7PuCG3R.jpg', NULL, NULL, NULL, 'Indonesia', 1, '2026-01-14 01:33:49', '2026-01-14 01:36:39', '2026-01-14 01:36:39'),
(6, '59291919236', 'Doraemon Komik', 'doraemon-komik', 9, 'Fujiko F. Fujio', 'PT Elex Media Computindo', '2000', 1, 1, 'softcopy', NULL, 'books/covers/ffTTP3yQ1iaX9TtZVROBTE60CjgHaqgFD6oVIboL.jpg', 'lorem 123', NULL, NULL, 'Indonesia', 1, '2026-01-14 02:19:22', '2026-01-14 02:32:46', NULL),
(7, '2305006', 'Dragon Ball', 'dragon-ball', 9, 'Akira Tokiyama', 'Elex Media Komputindo', '2020', 100, 100, 'hardcopy', NULL, 'books/covers/5NRU9P30YDO11K2shRfWrbMbDKfFbHytxEvfuNse.png', NULL, NULL, NULL, 'Indonesia', 1, '2026-01-14 02:25:01', '2026-01-14 02:25:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `id` bigint UNSIGNED NOT NULL,
  `borrow_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `book_id` bigint UNSIGNED NOT NULL,
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','borrowed','returned','late','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_extended` tinyint(1) NOT NULL DEFAULT '0',
  `extended_date` date DEFAULT NULL,
  `extended_due_date` date DEFAULT NULL,
  `late_days` int NOT NULL DEFAULT '0',
  `fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fine_paid` tinyint(1) NOT NULL DEFAULT '0',
  `approved_at` datetime DEFAULT NULL,
  `borrowed_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `extended_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `borrow_code`, `user_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`, `is_extended`, `extended_date`, `extended_due_date`, `late_days`, `fine_amount`, `fine_paid`, `approved_at`, `borrowed_at`, `returned_at`, `rejected_at`, `rejection_reason`, `extended_at`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 'BOR-VF5GCM-20260114', 3, 1, '2026-01-14', '2026-01-21', '2026-01-31', 'borrowed', 0, NULL, NULL, 0, 0.00, 0, '2026-01-14 09:00:55', '2026-01-14 09:01:38', NULL, NULL, NULL, NULL, '{\"previous_borrowings_count\":0,\"request_ip\":\"127.0.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"requested_borrow_date\":null,\"requested_return_date\":null}', '2026-01-14 01:58:26', '2026-01-14 10:03:31', NULL),
(3, 'BOR-GYJFFL-20260114', 3, 6, '2026-01-14', '2026-01-21', '2026-01-14', 'returned', 0, NULL, NULL, 0, 0.00, 0, '2026-01-14 09:26:58', '2026-01-14 09:27:03', '2026-01-14 09:32:46', NULL, NULL, NULL, '{\"previous_borrowings_count\":0,\"request_ip\":\"127.0.0.1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/143.0.0.0 Safari\\/537.36 Edg\\/143.0.0.0\",\"requested_borrow_date\":null,\"requested_return_date\":null}', '2026-01-14 02:20:22', '2026-01-14 02:32:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-2EzeMoAt8yjkAyyG', 's:7:\"forever\";', 2083742925),
('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab', 'i:1;', 1768383226),
('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer', 'i:1768383226;', 1768383226),
('laravel-cache-8pNEPKDrPWzYCIWE', 's:7:\"forever\";', 2083739857),
('laravel-cache-BoOa9OujsxndcDxU', 's:7:\"forever\";', 2083742460),
('laravel-cache-Ceyq65ZceWIly2yY', 's:7:\"forever\";', 2083742836),
('laravel-cache-ck0klX1fTTkYsZNa', 's:7:\"forever\";', 2083742550),
('laravel-cache-itLT4ETlauhZHOlL', 's:7:\"forever\";', 2083742385),
('laravel-cache-IUvcedqrjQHAOvix', 's:7:\"forever\";', 2083746482),
('laravel-cache-jbcjyULzz66ia0Li', 's:7:\"forever\";', 2083743901),
('laravel-cache-Qf1IvEmpHOxNPMLP', 's:7:\"forever\";', 2083741384),
('laravel-cache-VGkeRnFM7Es6GKfe', 's:7:\"forever\";', 2083743264),
('laravel-cache-XgHScZbhT0MtqtOx', 's:7:\"forever\";', 2083740692),
('laravel-cache-y3C9kM2UJkfMDlj1', 's:7:\"forever\";', 2083745458),
('laravel-cache-Y6kIjsmkUuZ5afq0', 's:7:\"forever\";', 2083741639),
('laravel-cache-yaRUgMvcbLVfE47E', 's:7:\"forever\";', 2083741156),
('laravel-cache-YM0E22AzhyKGzF3g', 's:7:\"forever\";', 2083740505);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('umum','penelitian','fiksi','non-fiksi','akademik','referensi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'umum',
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_borrow_days` int NOT NULL DEFAULT '7',
  `can_borrow` tinyint(1) NOT NULL DEFAULT '1',
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `type`, `description`, `max_borrow_days`, `can_borrow`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(8, 'Novel', 'novel-yI9r', 'fiksi', 'Kategori Novel Baru', 7, 1, 1, '2026-01-14 00:58:27', '2026-01-14 01:08:28', '2026-01-14 01:08:28'),
(9, 'Komik', 'komik-0rE6', 'fiksi', 'Kategori Komik', 7, 1, 1, '2026-01-14 01:07:05', '2026-01-14 01:07:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--

CREATE TABLE `fines` (
  `id` bigint UNSIGNED NOT NULL,
  `borrowing_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `late_days` int NOT NULL,
  `fine_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `status` enum('unpaid','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_12_06_093307_create_personal_access_tokens_table', 1),
(5, '2025_12_06_093359_create_categories_table', 1),
(6, '2025_12_06_093430_create_books_table', 1),
(7, '2025_12_06_093758_create_borrowings_table', 1),
(8, '2025_12_06_093856_create_fines_table', 1),
(9, '2025_12_09_095641_modify_borrowings_table', 1),
(10, '2025_12_10_103444_add_missing_columns_to_borrowings_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('0fdOZ0H7Dk5vmRw9Q4QdSHtDsSPD2B9I8Ntdexce', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiR3RacjR1WHlNd0M0WkZzYW9ZRmw4YWRoTUlNVTh3bDRkSXRJdE91RCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNTQ0MzY5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382545),
('0I7bZv9fTCWtMHpmqEBAGrKFQHoUP9WpUUMypZn8', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieXZlVzdWbjdJbGdyc1BzTldsS2lXTWlDdUQ0d2xYVmNxTkdQMFEwaSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDE0MTgwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381414),
('0rqK63XBeWwuqy55nTZagds5eMN7JAsvDa3g4osG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZVNHc093R2hnUlBxV0FOR3FXd000Mlg3TXkzTUpwWUNiemlzWnhQayI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MjkwNDk3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384293),
('1ACqLW0eWZHWhVXIxPGuinqqUTzUCKnHYBFT80Wj', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibnJZY0dlMHVwWUV5ZmYzdEVkVDZxdHVBUGlOWDRkRDZQWjNIaUVCUiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTkzNjE1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380994),
('1GSdVnRyFSuUotqFCOjTItdB2SpdmJZupV3GUFW5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRXNmNFpNMEJhMXBiQlZ4bTBoTFY5VTZxQ0V5OWF2QWVyU3JYM0ZVcCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODIzMTEwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383823),
('1xo8Feb0zKrX0rwhErURwt8I541RWxt9JpxWBEdh', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieDlJQm1zTXdBZjR2d1dHTkZXNVl2Y1pVckpQSjlrM2pjcU5wa3ZQZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDIyNTQyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384022),
('29YdYg4IbVGtuHwLuF4I6SdI8NJ7ThouzlI0YNZQ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ1RtMjVLTVhFOUV0YlJLMnVtTWNzNWZBTVptS3JQMDdSQ2FxaDJ6TiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDE3OTcyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382418),
('2AdY9Db3OheuGEOKEWoEI2wveGMTbjRh4ZaN2RKl', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSmtiRmtSZnI0MUpYS2VtU1BGclEwZXhvaEtWdzlDYXlqbUFRZjhkbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzMjgzNDIxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383284),
('3OHvtsrPTl6NNOWWeEbbPANyHZTRR5VAHiEoSH7R', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoialMzb3UzYkZzQUhONzlwUDdMbjk0bmRaQ3BMeTJ0TWVyZ1lyN002WSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MjkwNDkzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384292),
('3YUMAFGMaXdHBNRMgTYRWN4aM3OW9mBWWrlk8tQk', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWUZJYVBodlJwWThrQ1Rad0FkcFVKT1MxVkRndGxKNndaTDJiaGZyZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODgzODI0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383885),
('4Jr7Yvn0qUGvhPFMin3UzelatYrMU1j4hXsMlXbm', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiT1FTNDVzdDE1VkI1Mnd6VFBXZ2k5R2F6RkRKa2k5SlQ2MGEyZ2tTZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg1ODc3MjQzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385877),
('4NNg3VqVt1I3HAHzA68wJ5nHFrVUad0D6Dq1ddnF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSjI5MHQycm54ckw2YnZsaEMyUGswbDJScEJEM3B0SDlLY2w3bjhoVCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDI4MzA4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382428),
('4qrzIxPBY8FmxedkLmbCSgjKB1b7iHr0MHq9uLin', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaDdJdjFZeHc3VVhwT0pGNjZxSVIycjdxZHVqRU02RzVFQVpUNUhIUyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTc0ODUxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380975),
('4rci1Jar0ethu8Lj55I3MwRifUxFDTGOnGEHh5rs', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTW9JWWVxSWJJVTd5NnlGMVV3aGdjbkl4R3RmendlMjdUa21jcGlBWSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1OTEwNDc3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385910),
('523b5SnNcjUPsBlFu1a2ZWYP1JRS8XrS2E5MRpNo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMThEaHIzRkI3SENVRDVMRm5mN1plOExlQ1oyTWpIZWNidjZIcDZWOSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODE1ODYwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383816),
('5D7IE7l1mYsxRmkMSNHt97V4mf9R7WqJgDW5msdu', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSnJjcDlLWUd6Vkc4Tk14NngzQnpMeFFEWWRqT3lTV3RJdnVtZjZXMCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDM3NjU0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384038),
('5ip2ZvLe8IRfnbyG7WcrsvUlkRLWs8uKjSkVsTck', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiM1ZwdVlaRWlWNjRMMkNQMnN5SUpySlhVd3pBbXgwMG9vbDVZSGl1RyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODMwNjUwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383830),
('5qUVddjcqbT1cilEoXSwDQY63PWMmdkzEK7wj1Th', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTXloNGpoTUdZNVc5S3A1dDBiWWlubjRZMVpZV3dvbml2YmlzUUYzNyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDgwMDk4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381480),
('5UtnaIG6yQ4isDjqUL0QlvxZxbrbzrfmXvXDMxUY', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ3p1WlNSTlFHU0lyTUJiMjhITnZiWERQSkl6dUpLY1NEbW1wRkJBQyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyOTcxMzkwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382971),
('6hmt4M0k3IDd8szMhlECYzmjjdwa6JCzGMlgwlOR', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRWdJWGxFUHd1ak80cWQ0emVUb2Z4eTRuYk5RN3BxTVZxa3V6MGpoQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjgzNDI5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383285),
('6mDuCH8z8JTyiFrnGxD2HZjO82UQ51WbT6urlbZ5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWGJOdGNPbzlqejI4aUgyNUtxT2hadkEwQmhhNXpBVXNpam5mNzhkUiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMjk5NTk3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381300),
('6RiBOjyqwr1pkzYdMcRaJPAyAbrxtWpn5RbvzSzq', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWnRrbnlydWRoU3Jyb3l4eW5QR1o3OEREcHkzYVRJVHh6Y2FKVVMyUSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDIwMjc2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382420),
('73rhSLWpiUi1n8kbzQ1bq6u3EHyoV5pjD6D3vIne', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiellxWFJKMHp2SldhOTJ3eWlxSlh4bkdTVWRaMDRWbzZUN0R1YU9KSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDcwMTk3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381470),
('7bl0ab4flBmj76GW3Y7bRRaC31QOHGS91isefMiE', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRVAyczUyRE5QcUh2UVZseFZBSnhiSHQ3MWVvVFZQekp5Z1FmOXRlTiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzc5NjQ2ODA5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768379647),
('7woDw88B2xcDQV0QNjwCfVC8yzd6SlQFBXRSgevF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiT0FRQlJYM0tkZElMUzlIbjl5b2x1T1IwSThLNm81TWZWS2lET0lZRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDg4NDg1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381488),
('7yjaTRVGlC2PnecK5n9jfvVbbhZJxidzjpwlLE61', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMG9VdDBuVUxlRWN3QXh2b1FXcUFFYWhBOFlsb0lNMkRFQ21jYzJrdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0OTI4MDc0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384928),
('8BBM0dDe9sQHbevXRHQGFFkUydDNN7NzTJQH6yhX', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMGkydERNVUUySXJPZ3JFMkhyeEgzeVVwTDVJVGUyY3d5czBBYkVsViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDE2ODk2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384018),
('8KMnsN2EZmJySUKcEEEXOij5ePeWSxag09lcD3yX', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYmMxRENkWWhDcXU4cFZ6TDZ6eWxPWTlsRVRWdWs2dTZ6M0l4NnNlQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDA3MDA5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381407),
('8Y6vANqApqgGgGTEIkieFfeh0z6hMdmn2wyRrzVz', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibU85OHExbGxpM21rT05PRkN5c0pjeUpjVlN2SnlWZEVGcnR1MEFyZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNTQ3MzMxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381547),
('8ZrVRHItutEuMRJuAtXqYJS1DAdPLVTMMlG6KwTM', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiamRQaU8zckdaNXRsMHdRTXJ5V2hvbEFNTURHOEZ5dlVlYlc4THJ3YiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyOTIxNTExIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382921),
('95vF3Szd7z4hXOmUFcHNE2m7UlUG6IZ5O3jxqjtB', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNDU0OHFZNGxrZ2R0T1lCWlpJWEdkaTdzU3pSUWVhNHFrWHdLRlJyaiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1ODc3NDk5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385879),
('9aKYkAvSOXrcvKHvoeUDz9uUHHZQ3MiaEKXwmFya', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaUtTcWRXUExRTTVJVVFGQWZPTElTMjVNVDBZMkxKS2FOR1FKSTM4NiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1ODc3Mjk1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385878),
('9KmKKqPllfSahxFaIHhFQHPsiPfRrblBi7fbjvws', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMHQ4TTVETzlpZkF6VXRnSE5RWWlVN0JQdTk3dXZzSDlueEhBMlVTdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODY5NTg3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382869),
('9RyIVWQXPFqBa5fq0FgXKSYVavWgfT76IK2JyEox', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRzZ1d2NBR0d5Y05zYWJ2cXpGaU82NHN4ZWQwSnhPZXhQRHFjaXJkcyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1ODgwMzY5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385880),
('9w9dhNbxVfgRIfnRIQPRVyzhSwuAUjD6yZTx2DyG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWkd0SGpBc3pvMDVodm0zdjJxNWlIbXJUTDdvMjhEZHZ2SFBBR0h6NyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyOTUxODEzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382952),
('9xQlJg5diNRV9tVepmoVwjEIPL1IycaCJfzx9T3o', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaWZ5TE9DQU5JcFdUSHN2c240SnlzZ3d2bnpYd0lTSUY3UjB5am9UMyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDcxMTAyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381071),
('A1MG5oIWHI5KilFWkvPYzYFP2DXZzhU210LTdOiI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ3BKSGtTNEJuOW8zSjNzQ3ZaWkd2eVVXZVE2SzhuUFE5bDFpTlNsRyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDA2NjgxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382407),
('A53Pctvy5Va4znCXglR4uz3HSaYwaXKM8cnOqgKI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiU2lSSGpHZWJaalN6Z1ZtUjhsMTh3ZER2ZzFHNFI2NDhvMEJ1cFhubSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjI1NTMyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383226),
('a5ZJ7VWXJrYs6Qhwe68Z7oXL1LkgPWaDqFOjTUlK', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidjdxMDEzMFFyWGtWWEczU3hJZHFBU1l3SHhxbThQVHl0M1NIZTI5YyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODgzNTg5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383884),
('aasURqmVkcIwpaXrkhipOSlVnQwGBfCF3TEYCVvR', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibXFGQmpUQXV0dno4T1VrOXZHZFpmRlV4WGNVTXJMQ2ZtaEdFTkNYVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODgzODE1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383885),
('adW4TyUs4AvOJQo2F04OYKgTWDDyQPsg85OOKyNq', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUnVON2tTU2Y4S1g0dTZycE1GNTRidVVPUGZIOW1zcWdMSHRnYkEyQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyODU5NTk0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382861),
('AfjKYVh65zEsnz306H5qSaIWgubezlXI0hfI5X2D', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicWVRcDdpRXFLaHdUbU5mT3Z5SjZoMG9MWmdEOFVuMFlZeGR1cGt3cSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyODU5NTg1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382860),
('AfrXVfCxPMOhWuZ11KYpySxLFfJa7Xug3lj8FYTp', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYzFYdVROYlllc0NvenB4ZFBBTFZkRU9LemNLTEZ4NE1pT2o5bnJXMiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMjE0NDY0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381214),
('AGNt3cg13RAXtdzZtn7odCnnceImadrk03yRbS6M', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiOFJuMXRWWGY3bFhmcGY3ZFh5dzZvVmNob012STlSZENHdjVtUWJDZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDE0NjcyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382415),
('ahFCSf2qVCCNnxI86LD0MPHXn01lU36350bN4Cwn', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoib0VOeUx4Z1lQQThSR3FJUEhucFA0MTBlbHRsTDBrc01LakJlSEZtMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMTA3MDYwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381107),
('aJSljCIveKYPGPIrGKLGcZS1uZyQYZrJqr7mSP73', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUk9aSVZEUzNhUW9GNEk0VU84R3d0d2tYaGF0TFFHbzhuZ0tvWGRReCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg2MTc5ODM2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768386180),
('aT5SmXMBuEnx5HgLNAgIu5bZtqtNbBwD3NnVgz9d', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoick1tSHpOSWVjSXpvUmRwaDlCcExWajdFb3pZVUNiYm54SU9vOVc3TyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDgxMDI4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381081),
('BbaDhVh5HjmFGfiqccdwr5VDJLHcre4sjOh7TFHN', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSkl1c2FMSXFYQjNOR3N0THk4RVdHdUlDZkcwRmFSQ2JrWU1FM1F5OCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MjkwMjEyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384291),
('bdKPlC0DXSSlZfI3WxQX6NTil8TaRXPEgymwUGcG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieFp6a09vTTJMV2ZOUzFMV2g5bmRHQTVUQ0lveFZMRGJ4TGJMOVk2WSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyOTcxNDY0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382972),
('bDVy5OR6ODppnd4slUHg57CSozz4vDNLNA4Zm72N', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVU95VGpPSERaTWVtVGxlbHQ3djZZNzlkSmoyb1FXQVBkaXp4TFdmbSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyOTUxODQ5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382952),
('Be6xSG0QZccNpidwdufuyll7xHozwXBWx3vm5tgG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidUszZ3FiMFdNSmJ6VDQ0elVwWWZ2STFObzhHNktJNmpOSWhoalU1VSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MTM1ODk3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384136),
('BscacsiUI9zbfe1grxBAua7SpMpk4ZD10coSAZ9J', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiM2Zvbk9ZbjJ6TGV5UFFRWk1WT1RRek96NURPaDZnRFlBSkdGRzFpOSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTA2OTE4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380907),
('BslqDI9QP9nKmmMjGx1lxJjJTstgoxPK4lNFhvMH', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVUo4UTM0dFpOV1pENXg0SmRBV2o1MENBNGI1S2tPZEJaNFI5WU94QSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDE2NjU5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384017),
('BYwTZYeax3P2PmaXLDSo2o5YdvScy1kBH5sBJ4Sv', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSDVGRlg0a0I5QXRLY2dMTHNDOXJHSDBROXdrSnZGMllYYWNXYk5hTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1MjgyNzI2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385282),
('c2nNgriNNCIZDHoQuTDo3RLv9DoIO1hQGSgRYHPq', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVlpmQXNQeGl5b2RJZXg0NWVsMzlkSXNnbVVGWlZhRXltNjFsdmVwZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDU0NTUwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384054),
('ccFGjv6PiBB4N1JR6Ua1TsL7pM4H07Pmj7FJ34ZF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMHk3Mk8wY0VuaHlNbmxteUg3VTg2YWlPQUFQWXc2S3VuSXZnQlhTZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0NDg5ODcxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384490),
('CDSX9A2Ze2kwPYAdY08WO6z4QgBpLjQhnkx456Ej', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVGR1SkVhVXFuSVh1UVF3MFBnbm9VeTZsbDI4ZDFzcGNydW9abTJ5aSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyOTUxNzc3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382952),
('CEYrZHwtBQdzFCnDrcBKOlPKBHLZDJRrkVlmI4XF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSmhaTTN4MVRWbzlNYTdTWVVucHVlUzdTZVhIUVk4S20wczN5eEFvSiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDU1MzY0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384055),
('CgeK4g9nKL1cNFVJECDphb1oEfaJjcQzDUAyPfOn', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMk5Gd09ZZXNzb0xrdmFyVU9UOURTTEVnN0gxU3hNM2VHSkJhRWVOYiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDcwODA2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381071),
('COOqVhWG7Bc1hZlP1YtRj7xpU5Kqs9LDQkOQQNYG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidmJnaDBoVUpTaDJMaDhxaVpCNXRoNFNiZGtMUWpWbUVIcGhwMG1DWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDE0Mzk4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382415),
('D5KikYxrPfNjXGs95myIFf61p18ws9vasL8ML7zC', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibXdtcXB3M0VNZnBFbDhnbEIyVlFYaUhPMlBlNlh0R2tGUjBUaVNnYyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzc5ODAxMTU1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768379801),
('d7zbAjltdx7PUBkG36Ry6xpWV2Fv3YPs93y5sFbJ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieHQxNWZBeGZ3Y2JkMDFLYkM4ZTFMTkMyOXFtWFpvc0ZsUnJIRGlGNCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNTQ0MDY1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382544),
('DfbqbMgKk5B4t74N6KznCRFzwgKMioRa4pvQO4kt', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUHMzVm5sZTd3c1ZoQzBZUmRpdVFBUEE4a0k1bTFtS2w0WVY3ZGREZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODIzMTA3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383823),
('dI1VvG7pgSBuL79bNsxTeJFWAkhaP0WwZGnUolFI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidEJmOWcyQTg4ZlhHNmZBdnNlZ1BYd3dvZENlb2txQXJFSW5XOWJDTCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODE2MjIzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383817),
('dj2aW7pZlQv3ZfV2r21KK9ISU7Cm83npn9hKCgZ7', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSE1DQnZEeE1PQ2UzQ1Iyb0xQMU5jQkptQ213OUZNS0FtdXF6czdsaCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNTQ0Mzc1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382545),
('dJnfwoxOk5l3xvGZFNrYGsepViLFlCOVRXiD8j9Y', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUHpYemttOW8xVUdJMFNtVWFXTEd0TlBEN0FaeFJTRWVSSmZobU5FViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDA4MzQyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381008),
('DMrRVSPKJMzIjCJJ63caccp5yuKmMAPUbvkKXxw5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaDhiR09OeFNsamlqQlMyd25JWVhLbWJTSElVRkRXYlN2bUNOZ2daeSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0ODg3MjI3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384888),
('dPULNBR6zkgUtXWjGML3A5k3BYYXxmtD1OXKm0vy', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWnExaEg5ekJFUHoyaFpqRGVnVlF1SG9EMnBTckFZTGdvNXV3WGp1UCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyODI4NDc2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382829),
('dQ6jl3QJ84KQm1gG4oLIz0byQxtJpV4z07Wc6hDz', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNTBUQzJibnVnMVl4dmJ1MUJjOG5oQWxWMU5LWEkwT2ZQY3BGRGhPTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDE0NjY3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382415),
('DTNv42mudambj6DqvzezVFDpapLpIRFzIBi2TupG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMXhtWExCSlFOVzE3WDhxM2RZTXV4bnFrSHdFajc4UDFuZlJzRE9RRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwODg0NTI0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380885),
('DX03jkrBgZE5GcPQANEbgCSrrrn73LkSNwzHZWjM', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVTExTzduZktTU01kSWs0VXpWcGRLMnJudk1SaEY0OUdhWXA2bURYbSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODgwNTUzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382880),
('eHSU9MUA0EHAs9d9OyruMpqA8bosQiXDe2VWvM6G', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZElxTExnODVWNzV1VlNCam1yRTFSbU5HUHdyaW1WVERFdG8wWjRvdiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzc5MDgxOTg1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768379082),
('EIuDCAHvR5Ll350HXSVDXUF3MCdFmjo52MPyDecO', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZm5yd0JZQjkzMUZ1VHlmaVhHcHlaMlV3UWhZTWpCTHY5V3A1Zk9rRyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyMzgwMzQ1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382380),
('EW5ga81LPASN58w3CaoqcHnkK0VY4RWq2TATW0tW', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSzlmb3NlUHNvbHljUWZRSllXVXhRekdOMkV4UDdOYkJEdzFiVkJWMCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzc4NTA2NzI4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768378507),
('F3gNV1ZLZNkw7n4u9aH7SL4M8U7kC0XSx6ZynI3r', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMUl2VHljQkdKNEprbEFRajZaMVJvTXUxVHZabDR0SHRqVmRydnZQOCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjg2NzI3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383286),
('F8BBOH5VKZKfdFhi6GUKgQuPf2YVSn2pfQMTWdGH', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicjJHbzNoTGJsaDZwelU2Q0hoMkxvQzRYQ2FPeW9TTzNNTHJ2NUFEdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MjkwMTg4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384291),
('f9rM9qEwYBpPBt3vqXcY57vwUPKEoBlGrIr0k2GY', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUGJPYjFvcWR2UHlNTkpNRnpKQzg3cWI5czdtcjhJRFhPWFpEWXZ5OCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg0MDE2NjM1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384017),
('fIQBIkDYMoLVcA62E2dBMvaJYyIdvrZweLNt33MC', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieVczQUdFWFI5RkU2cjJFT3A1MGRvZUFaTG9GN01kM1lKbENocFZBOCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjk4MzE3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383298),
('FkgBMfYptlyrYSKeDA7EL3Zwnj9AyR7VDkAFCZr4', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ255cEtSdE1QM24wbkFyU0RPdkFPM3VPb1hwR0xiUnRJQjVmb25wNiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyOTE5NTY5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382919),
('FMBZs0PPlgb7uLCODiYwkTOpZ5koKeUVPSzp6Csb', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQXRJTDdvNndVeWduV0N2Q0dDS1RUdFJtU0I4ZUJpNUlPWE1DRmpCVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzc4OTU2MzMxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768378956),
('Gb2xGgzZnNxZjmv6neYisqNVtRg8pyWPMOT0rr3U', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoid2Z0MmQwd3VNcnA2UFRYamJWWWN0SnhBelRVcFU0SmJRVmt2RThRWSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODE2MjE5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383817),
('Gb7lqTyMqMPv6Z65ACpcBRwi8UxCUzfTHyYMkHZG', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWTdrMkxuR0gwUzhRckZFeXpvbmR6REV6MG9rWDNYdVZGTmI2cnZsUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyOTcxNDI4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382971),
('gBCtBzIGTUwCuIEjGB1KgbmH2TpWgptTk7h3ctXM', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRHRzMUloYmpkUDhEdURNTUtkdk02RzhaTm9wYWE5U3JvcEVmcDVzMCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1NDE3NTg2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385417),
('gBWsUJRZdSghC9asgevrHkxXlrH3rJbsElCJp0Vc', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRFVmcHZSMW1OQzAyY0FTZllzeVU0QllnZGRReERrc29XUkFGREljeiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MzA3OTY3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384308),
('gMLuyU53BvV1OOEtnnycwh3meotlq2bgr4Teuue9', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoid29haWZiVFNkZXZsMEEyYnJFYjJkejFXNFhwWmsybVp2RUFIZzFvMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMjU2ODEzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381257);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('gPM83XDe4AwBYO8mNsPi1UKl3D5WntlYoa7qKx5h', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiM1lXTzJGdEMxeWpPQ1NBRGFmMU9uRzJxV29TWVNkeFNuRG1IdDlROSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg0ODg2OTQ4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384887),
('GsPvihnR0Lt3uMoyth1b7Hu5aBkv3Zu3Vq5G7xov', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWTdWZmNraHRURzMzTzZ5cEFHaGJZcEVqSXBmRjYycUNtRXJIS1AxUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNTQ0MDg4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382544),
('gtfCFspueHwROJUaVZIs0dF7VvF8XNaLSKHkYZou', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibG5yd2M0bTk3QVJuOUJydFI1aXA5VFVJd01WQzV1SjhxUjdFelRaUSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMTc5NzIyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381180),
('HAJmopJRAR7JREcL1IHx3LkrWoKFQIQ9YTMpVNmk', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUnROY29oTThnWG5ON3lvVXczN25kWVgxQ0JCYlBDODl6dHRFQWxvaiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzMjgzMjE2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383283),
('hAJydfuGbaALPaOOgm5ExPYmkljknejdZ7w8tcZW', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiV05Ic0FEQ3ZZNm9mSzllM0pqTThVc2xWTmoycENCRmpvVUlHSWZSZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg0MDE2ODkxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384017),
('hcfBNiSQvI9KNzj087sNwZsPGN9Y9C7vEea4Y0qq', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSlQ2OFFnTlBDNE91Z0daMEh1dURKSkhwd1JtdFhUNWg5MXBYTGI3SSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDY3MTg5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381467),
('hf3ysqajqWvlHOqROxvLK53vOcVB2tZUeXf4MKTi', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoib0xra1hnVGdoemRoWjBUd0Z3ellRR0ZkV281VVZoV3RWeExHNFNCYSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDA3MDAxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382407),
('HfUfv1T4SkoTOCe1lx3NoVf5jdW6jLRLvzvMV3sQ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiT243R0FZZUp2b1VnNHVYakZlemVkdW5mUU9KNDFreGg1N0dvNkVJdSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0NzUwMDY1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384750),
('HiOgAKM50B7MFWFoC6a0QgrOuzcfqkHaZCSL55yg', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicjRnZnNXaFZlTG5CUVdOSm1qaE1BaTYzMlVvcEVpZVJLY0tkbDUxbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTEwOTk0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380911),
('HrMVtwgPVATmDcBqIk95xo7Mv0alBVBCTqcVG3RO', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaW1PQ2llRnBjQ1ZyT2FYNG5vQXBvbXVXUkcwZXdiZ2psV3pVS2xlbSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDQ5Nzk4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384050),
('hYQvM18B06HPX3aiNkelrLPVwSGOT3MtlLCna6bU', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNXVJRDNXUWl6UFhFUk1Ca1VzbVpDZndSc1lqclZqTTZQeUZzQlBheiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODIwNTQyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383820),
('iDy6Fr7YzgK3WYyG2hwDEznkZyTujdL33qIJtEOl', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTHV6T2s5WndhRFNzMVV1VWVGVEwxTUFqbTM4UndYMDJkRktkSFUyNiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjgzMjM5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383284),
('IOHUtsen7ZfPjHAQXm3btZpqLQlEuJ7iYIlGkwFZ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTGtwOGNsc3Q4Mk9UbkVTdTJmTW43ZUN4cGhsTm44SE5jSXlKMFRNUSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNzEwNTQ2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382711),
('iReIlNFNoek5dKX5bVDOcvEwTVIt3dL1p2yR7tZo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUXIyc2xNS1pRS05jYUEwNTVvdTF1S1c1WDQxek5oZGJoOUoxRXVLUyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODgzNjA3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383884),
('j1NbJIZ2pzzpDD7IwQB6KYcRo1NYWejp28V6jRMD', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWEs4QXo3V0xKcUpXM1lTZFh3T3lKdDduZlFsSFlSZTVCa05CMFR6byI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTcwNjk5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380971),
('JaP8FuFesepgeblCzbMsV8p1vEiVKkomuZIIuXIj', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiN0ptZDMwYlZNcXpBNnQ3V0pxeXQxc2llNUJIbFJwYTVZcTJySDVrdiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyODI4NTEzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382829),
('jAQIUgqulnPU7b7PMi9AI5gu7QqdZ8fKpNem8SMr', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ0ZHSkE0aDRybmtFYkM4bGt2UHd2UGFTbUp5aEZJY29GbEZxSFhCTyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODgzNjI2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383884),
('JbVFFwgcxZbnMqleRmxB3iQcO99y21FNEBAipMv1', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVmw4VXpZcTdUaHBuTjBMUjkycDZrY2VJR3R5ek45SGhlZ2toMHRmaiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyODU5MzI1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382859),
('JgPTNUjQ0Yk8j9qCQhFFcKJtOkFhFrmUCR8Abwtk', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiV1FuRVBCblhCVzdXMjVyMGZCZXlHVDhUMHFkUk43VUVodHozR08yciI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODE2MjEzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383817),
('jGrSDaQHRp4WcORxrwwrCP4clLrnN92hup1rELoe', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYXRNUzZGRDViNzU3MGxQWUN4d0VKRUFCamJCeU14eVprRkRMTlBTUiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODE1ODQyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383816),
('JhqAh4IHnUbmNacvqNmxkDYL5TAP2P89JWssAXft', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYVZhNjltNXFpdlZHRzEyZklJQmwxREdFV1dOaHo5VnBXTUswQlF6WiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg0MjkwNDg4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384292),
('JLBhr2vv6F4Nrsfuv5dqdTJQRLj9rKXbWSiunBMS', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiN2ZIRGhaVXY4UmxRSThTVWVKY3Z3ZW1yNk02NU5VaHllTnVCWDRSSCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDEzMzk4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381413),
('JmRlWmITK4o6IJxfGG6jVrdsrifCsPI7YSG2N5HT', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYnpVM2J0UDJGUjVIb05wbEsxNDNqd0Foc2podGlQdjJhZ00ydUlBSyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODIwNTQ2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383821),
('k0w4cp8ED2O40yycmD1VNa7HSXuZbkhY2biJLbzW', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiU1pSM21oRzJqWGdneVNvMDQxWHdvclVURzl5VzBkQ3RWSjJ3WEFidiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODYyNTU3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383864),
('K6dzclQHeiu01oDKXGLKs9fk3o493BYll7h8stJn', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQXRaa3pOdWw3cUx1WWR2d3FkMWFMTHVLVW56RU9QNmVrbUhEVXFRNCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjgzNDI1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383284),
('k6tblKT6kWqWyeRbdkUpScZVK9gXMhuyikzw9scd', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoia1N4SmFzM09rRldXcDhIMkw1TktmUUliZW5hUDJhd1BJcDZGZTdheSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg0ODg3MjEyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384888),
('KAivro04cax7AaJTi3tWjJ1zCdJTYJ6Zksg6orAh', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidXVBbE81OEo4eUxRUjZaWU1KWThDV1NLVUx3M2RTZVcwdExvUTBITyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDIyOTMxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382423),
('KHLjXhlP62roPpVz8JJbcv1FkPT4ImtG4whV7C9M', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYXZHZlpKYlNwTlVhM1JOcGVUUmt1SkRFbUhBclBzaGRXU3pDdmpnWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg0MjkwMTYyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384290),
('KKle537yiTTOOlE4L6Jdni0RkZpPBmVRVxQSQgxZ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY3Exdm9Rakd6R0ladXI2eVJWYUFRR29zcWJoMGZ4bE85UDc1VTBKbiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg2MzYyNzgxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768386363),
('kM6475BUM1n66fGyes93U7blGLHafOloHrPZqndM', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNHg2WmVtQmdWS2cxOGRuNFZCN3ZXQzNUZk9MSjhHZVFkamFiTlRXMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODIzMTEzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383823),
('kNAb5d3pfOz4hCazNRcDDYwH5w3lb1mtT4hrVPXY', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSk9EQmtUdjV3bjNBb1paeGhXNXR2blFoRFFSZ1NOQXFUazVHVTZkSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyODU1NzM2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382856),
('koOpKR5WF9nPc3K0HPI9DzOWfuU2k9qrnWQhnWG3', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibWJBejU0Nlpnc0ZpTUJaNnlPNTR1cW9yVGpmZ25uME5jM0U0Rk91OCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg2MTk3NTc3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768386197),
('kWUOMrarBiwl5XeaNIGLoYbmcpNssVbrWGChz3NE', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiemtZNkRLSWFiNG9DTXJybXdQRzR2MzEzWDU3cXo3bDI3VWlZWmdNYSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDc2MzY1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381076),
('KxAPWuO2WtjvrzaCa7P8Jm2vt9A4jRtc7OAgs59D', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRlZlWFd3VFF6S1RBOWhDa0dnUFh2b1I3djhXUVZaUDBHZ29BbWxhSyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDc4MTQ4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381478),
('l97eYGe2szaJwTFiZPXJ4cKD1mO8N215LU8GDHaf', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTGxPWjlJTFNKcHpzTXNGWUV5TG1LbXlRSzhpRTlWUXh3QzF1MGU3YyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMTMzMDI4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381133),
('LBqyuHzhJZEKbcPzNzBxufPm6ewt1d42CG9riPMI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNmpndEJ2ZzlZa0h3bnVEWENCTVhtRDFLY3p1UEFUcWhFZkpTNmRNQSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDM3NTg0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382438),
('LD0v0BrCCCLw43sgxAuiaC0Jb40B9JvPAicWniox', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTnpXWDM2WlpibkNXaWl1dlNlaXZoWFB6VGtHemppM0hVSjJacjZzWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyMjgxNjY5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382282),
('lfkNZbzLoI4YoMlqpyLgYb4q3Dxb9skVwkgmpy15', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY1dsTUdGb28ybnZoYVplc0NZQW5qYXBNQzNOOEJCWExKa09xMjlsayI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODI1MDY5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383826),
('ltV7vH3TG6TNVsTea7TYwTfq0WXxggNvAGB14Ih5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ2UyREVqQ2hkQlAxUEthQzVXVGYyTGxNd0VUUXQ5T1p1VWpZOFA1ViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDkxNjM5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381491),
('m4ggPMEtdX1BVC7CHdcC2TMHOgsnccMLrMm4kg4N', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidTQ1N3Y0b3BZd3RWNmx4V0dXY1FzUWhYTFd2enZGSjg0NlBwOURPUiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODYyNTU0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383863),
('Ms6oLbZdSh23a2LkzOqqcUX3GoLXTejMTPdv1THE', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY2tTTmZib3RnR3g1OHFrOFFVZG9wWkdRYTllaTdJOElIQmw4aFFhbSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODI1MDYyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383825),
('nakzVzntHue2IVq57GqMIgtQA3O5ssb1qElwfGit', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoib2xhVWlSb3hEODRUZ3lmcUJpY2QxcWFSa04yVnUwMkJEYjhsQ1pBNiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODY4MzkwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382869),
('NfcB1MtYhXUfIj8ucdtnq4CaRq1i6Mzg2XjXQLfZ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMTczSHNjMXl0bG9raXNrQkpBb2NUWEQ4d2Y3aG83UHIzSUFIbm8xVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODYyNTUwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383863),
('NgzYGYgwr318OzGZ3oxijbJCpVlxgaRRFmp4Av1N', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVERsSERLNmt3VkJINUE5R2paN0Vqb0IwUXo5TWFvNlpadnVOeTlxZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDE0MzcxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382414),
('nM5CCIniYTBYAu1ZKQDyAmhcbgJHSlEIhvOsDSIw', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZnpVSmN2SUpuZDhBalNtdGVVbGM4allVQTN5cFhMWXVneWROR2F2QSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1NDUyNzY0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385453),
('NQ3RFvlkP8Cgu2DFAIqaq6mBLNza6Gae3VtZAyZw', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibExrbGJ4cFdZamYwWEZXS0hVSnBmMk55OUpXa2N4SGxZanhHNEtIZSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjgzMjU2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383284),
('NQgRgzoXa4ildnZefI2Al7TqIjCIpz9LAEDcsoqV', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieUhMWXJUMW15UDJndG5qaU5LbkQ0M3dqM0JOMUtBV2d0S2hWdTF6aSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODU5MzUwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382860),
('nRkFQblLNjpL1Rrmgp7ZTG8LvUutDxWc7EBgMpgV', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZVdDaUppVFlocFY5SDRqRlM5Y1BqbzJCZmxEd210cXkyYTRRTU0wNCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTc1Nzk5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380976),
('nw4lA8BZg5aFbTOIGpFGi705iX33ELXwnbsL08GF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNmlhZ1J6VHdrbFBjMUhsbGdEYUJQNDlLTE45UDAwQjNIZEZHZkxRVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODI1MDY1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383825),
('nXuJqnlyCUpvjeuTu0E36beXw0eXU02BnxPxsPV1', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieXRqQ1lhUGx6RnY3UFd4Y25mV254enljR3B0ZHo2Y0szYTliSEkwbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODgzODIwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383885),
('o42FkXjd408Bd6qho2ppGil755zqbvJn19d8zfWB', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYWd6TEFXS3pBc0hUVmhaa3B2NW8wRjRwNG9hYVdVYjBVa1M4VzJNSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNTQ4NTg2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381548),
('o4EzeeKZVZs3QnNdUbQgiOHIX5BXYqKINwVPkscD', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaUliejBKT0ZJcldHTHRreXBlMTdNUFlSeUViMnd4SVpQN2hSejBUYSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDA2NzEwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382407),
('oeTKAAao0gLbRVlCYYgDsEVp8UicioSpBaCDihMB', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicnA4SVNiSnJZTGxSaGRKUHM5ZTJTUFpVVXp1RzFEc2J1d2dtTXd5RyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMzU3MjU1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381357),
('OGKDeemq3zmUsPojfMHmB3xKXq8LMkuKbct84mTd', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiOFRPRmhPY2I1RkVSR0dZVUhwUkVhQ0NnTjdlMlVsblFBRDB5N0RxdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0Mjg0MjcyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384284),
('oHbNF2uLPB6qySIK9wGiwKyVDxpfKVJUSUBgwckY', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMHNhWkVtTkRRWlpmSmtzbUMyb3FjN1ZWWGhuT3ozcnhld254R1FpRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNTc2MzczIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382576),
('ohWVs3wmjEFm5UNQaMHMnToIRZDljfK6RggqtRso', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ1JFZEYwT25lbkp6SVIza1JjME1EV0RUSmxHTzB0ZFQwYzRYaElZMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODcyMzM2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382872),
('OY7O1sndX95oWMLAWn3xemC6MAEg77VWtD1w1x34', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaHg2eHRPQkc2Zmk2ODFjSDZtYkJGQzEwT0NlQTN3NVdwTnVoYkJKeiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0ODkxNzcyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384892),
('oYuIs0gikLxdjm30H1f1TjOLpHx0uL1i0eARmk9Y', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVGZuNEFXVWxVbDZrZEhzbFRleHBjRHg3R3JSRmY2VkVIbDRTeWJ0MCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODIwNTQ5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383821),
('oZhoF1e8GWDS12OPcJIGnIE3vhZflGGVHM2Taht2', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicEtaZENxcjhkM3FoM2dKaklGQ0lOQ0FMMExsR2ltRVBRZFowZFBIVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDI0MTc3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382424),
('P6ZSLGaIzAVygrksxxUz2zidW38q7QJf5NyxZGTd', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidEtCQzVQbWc4YTR6aGl1OVlkT3pHT0ZlOEgwZTFJbGttejkzV1dvZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyNzEwNDI4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382710),
('PgKWoTMEu0muAGtEpd4iXq3P5i1Z6dCyZ1rxinTw', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUzhUNVhYQWxNdnR3Y1AzMXpBeXJZTVhGMTFLcXAyQ2xzbFVhZmQxViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyMzgwMzk2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382380),
('plwmoRx0N72uz4eDA2HixNigHbrx9nhefJNCZ5M4', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQmNMb2MwUllQN01ERGE0WXRUb3JWMjNBUHdybm80VTEyczFYWW1WSCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzNDIxMjk0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383421),
('Qc1avjgWOyDCLrX3Y3r9AnupAFaMm7vCKP3ArY1Q', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieXpyalg1WnFCOWZuWkJ4ZFVsMWJpNUQwSXNETkVNOWNuUDZXc1JMciI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MTM2MjAzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384136),
('QDmBag04avK7BLLx9OqOIJ05hSbkvMnK1Msk8Wgr', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicjhKVkN2d3Q3S2N2bmNTUllvTkZqVTBoemR6bTNQc1I2a0cyQjhERCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwOTQxOTAwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380942),
('qmKlcRobhyCFNDZDFnpMH08s4nIr14fAwMqM4DBa', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibnVGUHNaN2ZxdldiYWo0OGl2azlDYXZUSFNJUkZmS0h0ejBobjF4dSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1MDUwOTQwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385051),
('QUfBuARlaTNuzzzfufv47oYWKNpdbiKqFL8sg1ZK', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWGRUWHJ6czJhekRHVlU3a29ZeHFGTDU4OVlmRWRjWHlBR3J0VDFPSCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDM3MDAzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382437),
('qvMxWcsY5mEmt2q84C4tZNrzDyMdQsVGtirnjB1A', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUlY2WnBBSlY0cTlwc2lHUkNKdWxKV1pTcjdIUWlvazNJcE81WnFmbSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyODU5MzcwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382860),
('R7PaLaUaeKsgiV1ur3zbNvRRWZlMA4TFQ1Cf9wav', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVXhiUnBCcDFld2xmdDlWeTV2M1psemYzckFkSEZoNU5sWDVqR1dIayI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDM4MzQ0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384038),
('rM397912M0D5wY0OcgvbAb1uLqvqdE8U2fgXAqCC', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUk5LMmVYWnBVWHRIVTN1dnBkS241c1hvUDVQZjc5OXl6ekQxWlJEVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzODYyMzM4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383863),
('RO2M74jSrmCTxBkExHDPPMRRiRl8U5fC3bbuLFrI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSU1nUVJjZVdPRHhXdzBQeVNIVmhJY09sS0tSc0NoMnhmNWlqYk5qdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNTc2MzM0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382576),
('RQbV9MIcW2IirTFfvUkpccwZN0o3kzTeRB2gxw4y', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMGRNemZjbktsS050Rzkzckc3c3lIcUlMSlhPVXJzS2pHODFacDNJYiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwNzkyMjg4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380793),
('RyTOs8KPBSjtIxN6yd1HMck71IFvOWDvGlSZ5rX5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWjdKVTBPcUdSU0djZlFOTzNGQk9yWmlZMUYxQjVoY0tiN0g1NGxxaiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODE1ODc3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383816),
('S4qQupKGZ1PXn2yBzKKZ284yoWwUf2yf4tjLHaZj', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiazM5aVZGcExRUUJMenNVQk5QdjZ3ZTJ6RHQ0anB2WG1GMmZXa3E2TSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg1ODc3NDg5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385878),
('SahIc3dQemrM4Y8LtgtppnSSMP66KDTmLgQCRspx', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSXhhNHlqZEpnUU1HeXdSOVp0ejhSMjRMZjlsc1pMRE54NkhNaGlHTiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDQ4ODk5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384049),
('Sm0yeMZB2jLk2L05iLCVN2WUudsDVWltCnbo8Wcc', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTlZQWDJESkJLcUVtMWFWZDc0N0kwSjlycnhrU29xc0VScUVscWN6VSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDE2OTAwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384018),
('SXSan9Sw6Q13zzc9Qad3GLk7yBwAvU6Qwu1CI3td', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidjJaSXAzcWZ1UmJFTWFybXlQbXpjSzlKMFhFYk82TmIzWktKVDdSQSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDY2MzY2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381466),
('T4VaTfdHA1rSj7PA8dAHbBaNTPkOsO1NynHp5kud', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWEV6bmxOa25VOGVsM2VHbzI0UW9xRURxOXVlWjRYS2pRalpsVU9XYyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzc5NzIyMjA0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768379722),
('t6kKrT9AfHq0KTk8zZHnVoFMFigcVX2zjVNTDE5l', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNmtKbVljUGtXdDVjUnZlN1dKb25ENUdHdFZXSnpPSW5xWjdsZ2xhRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDM3NTc3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382438),
('TeJRbhn9q56aZGGWg0XHXIZjQfRxYsPO0IL08qUT', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSWRKejBINElBSzlSREpaOXpuRGVLaDNpbnJGZVY5MGczQUx3NGNmayI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgyNDA3MDA4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382408),
('TnXscsNWVguQsdkKqfhr4LS2CpZRDtiZOllgY8Vo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTFZqWWxDZXp0NUlQYzJZSUwyV2kwbGVROG52UkRyWmk2YmlVelNLWCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyODU1NzIyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382855),
('TtIzSPPl4Pnq3YBQG71A7q6pPquXRQ8GddjiTgpy', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWEx4Q2xsWHZocFQwUGpjS0hkd0l0TTNsbzFEeHR5eDZEVURhRHdHVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDc2NjA0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381077),
('U41zVYz2sVdu6bGRTriGuzWnMl3G4TRFy8GtwOOO', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVVhLMzVkSGpkT3BUSXVEVGpSdGw2QzdqRDkzdzdPUEx2bWpFV005MSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg1ODc3MjczIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385878),
('UGd5eT2xKugB8Ct1ulH1qL2jXYddtjqbz5CM0YmI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMldoMU5JSHFxakpSWTdnZHlrUGw4NE83ZGRiZmFlWEJ2ZXN2QzA0MiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzODYyMzIxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383862),
('Um3IWgqloow99YlekcLNL1O1NzjNiQeszJ4EWx8O', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVUFUbkJYbkQxUHYycmZOMDgwOE9pVDlxeVF3bXhQZ1VRTmVkQ2VLUyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0ODg2OTc2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384887),
('VFT71xeWgmBtEuO6upkr7ubRD0vWGH2U1kqu0VqW', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWFVZQWt0TjRlbEF1TktXc3B5SUJ5RTlIaXJKazJzQ3lrVE0wRUNRcSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDc2NTgzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381477);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('vqCnMg1GJRBPKSUMnaFao9rp5RzRLNVg99lBS7E8', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieHl2UklMQU04dE9xWVJJTDRmeTlialdObG1FQVR1czhVaVB4TjdwVCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODI4NDQxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382828),
('VSV0rpCaEMl7AuO2737re9Iis1D73QbmvgndaWyQ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidlh2emNkUFdrTnUwQll3MFhsY096ZUQ4Z3Vxcm9kTUFoWXBENXF1NiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxNDY5ODg1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381470),
('VTNnTXoEEQ23VBTqVs4MbSi4t813ZEKa10vYgKGA', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTXQ1WGFsTU1kdUhDRENHN0RDYUJRbTRWSnAwbjZMcTRKVUNBV2RWZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg0MDE2Njc5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384017),
('WDaW6WRFsRSZsnArhBQl8yghmhHI5lFxYmcivN5h', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiV3ZoVTlhUXNDT1A3eVdMZkloS0lhY2dDNEIyT2RBU2FhNlNGYnlqeSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgzMjI1NDg0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383226),
('WoEW7YFXLJU4aU785z24S6rfv9c2dRxuVvIiUZ9u', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicGtBaXNHRUp5R1FuM3A4S204eEp0VzdIV3VmdmlxNkNGcklnc2x6dyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzODYyMzU2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383863),
('X3sdt32SnPUXDMvX1zvYhTWBdIv5fZbGcKMs9omx', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiU2ZDRnlLSVNXM1RtY3NSYnZvSmY5RVdRNmlaQ0tMdjlNdWZtNWtUTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNzEwNDkxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382711),
('X8kEL25grWClqGB8AWmesxb3ITq76VjBPz0ypNk6', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibHZhTXRCTXdab2Z3TXRhOUZrOFlUY1pNelJVTkE1Yk13aDdqajBsYSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDI3NTcxIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382427),
('Xko6rg9k4eBwBIYempmpY5a37hJUzvVRkkbHgvuv', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicmljckoxOU5UV2o2NEFyQkVRNDVxZUcwUVBSeEthMFIxazVwT1hSbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4Mzg1MzE1MDU5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385315),
('XOKMq3jOA443k0RYnQApMOyv47xCDdRrGERv5LLo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ2ZxdXpmTURMbTNUdTRUY2k5eFVmQk9JVDRoQ0NHSU1mVTBYTWcwQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4Mzg0ODg2OTA1IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384887),
('xR1VhsbUguWRhBrKTlWe5NhH1pkXqidiYrckIi3W', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSmtBTkpRbjRuOUg0ZEQ3aHVJUEpzcng2Z3FtOG9mSVRjMk1XR2RPUSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjk3NjU4IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383297),
('XT3GVzeNEjJnf6vsQFRugIlxPiFfA6zabHgBZeve', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoid1ZNVGhMUTdGNTV5R0lNbWNzMU9uR0tLclVjY1V5SmNVM1haNUpoQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg1ODc3NDk0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768385879),
('XUv6a7wSz6qvLPN607CmphHunw9SH9HHTHOyMdVt', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZWU5V2dMV0NHOGFETGhQdWRWandIWmtINFRXMDFGVFg0V1FDMFlBaiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODU5NTkwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382860),
('ybDyfShO1PBPBTF30OJUJfjZ4fNGGvjDIwU89xFd', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY0o0RUZDR3pZV01La0NtSVN5QmJNMkl1Y2xMYVYzNk9JM2NWNGdUeiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgyODc4NDA0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382878),
('yDfkRHx12cZ3JcPeC27iRcJ5OvtVW7btw3DT8hqo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicTNTcjBtbHRXdVZlcnBLYzF4Z1lHdVNtQ2dFSW1tR2xzbXBOMFJqQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMDc5NDkzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381080),
('YFCWAmivzPhXJ6ujZSizfqQ9emiHiJeVLYnqeUNE', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTjRWREVpRGQxYmRDaWlJNUthU25mbUxVZzk1SEVCaDE0aE1Xd3hFcSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL2ZmVFRQM3lRMWlhWDlUdFpWUk9CVEU2MENqZ0hhcWdGRDZvVklib0wuanBnP3RpbWVzdGFtcD0xNzY4MzgyNDM2OTcyIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768382437),
('YGHsoOdyOXmf0z3ny8Z6R1UsuXdD19P3tWrOMkvY', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZW5hOHdwVHoxUmRRNThGdTk5V2lYU2RNd0RJejlTaWFkOWFHaDRqMyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgxMTMyMjc2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768381132),
('YwMXgWTrnh1W1n4KzpyQ0SO40jsWls8Xodon2aiv', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQmxWd2NsRlcxT0tSRFl3VHJaNU5kTzlsVUNGNmdVb2F5azQ1NXE2aCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1ZsRWZhMVhmSGRUcUxndW5OeEtiVWhrQnRFTXp6TUFnTUEwTGthNEwucG5nP3RpbWVzdGFtcD0xNzY4MzgwNzkyODg5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768380794),
('Yzmfx6nQwfSkp412iPldAQxuKAvfXZPmPyXxRfli', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiM3hlaTVTY2U1cEYwNWdNUlBuc01DUG5HbEY4YTV5dkM0NzNPaE5IRiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4MzgzMjI1NDQ3IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768383225),
('zLSNuMGxTi75D15XBCbTaMBSt6AxXsdXDWsinqg2', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMlY2ZkxKSU04TzViODBzSkdXWHZmNDJ1WEN2WWJvUG5mUnBBcU5EYyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzLzVOUlU5UDMwWURPMTFLMnNoUmZXcmJNYkRLZkZiSHl0eEV2ZnVOc2UucG5nP3RpbWVzdGFtcD0xNzY4Mzg0ODg3MjE5IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768384888),
('ZxQAiIyIFs3cPwS9fHv6vcP4rYVzDAIvnhh7rLZF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiM01HZ2xiMlpuMGMwc1hBSXlzeEhaakdoczdiUG15QkNCc0dhZW1xVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTA5OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZmlsZXMvYm9va3MvY292ZXJzL1RTc0RyZGFnaFhvVWtHREs1WGZXVW1SVW1IMmcwa2UwVTdQdUNHM1IuanBnP3RpbWVzdGFtcD0xNzY4Mzc5NzIyMTY2IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1768379722);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `nim` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor Induk Mahasiswa, jika user adalah mahasiswa atau dosen. Jika admin maka null',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `tempat_lahir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `gender` enum('laki-laki','perempuan') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agama` enum('ISLAM','KRISTEN','HINDU','BUDDHA','KATOLIK','KONGHUCU') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('PENDING','ACTIVE','SUSPENDED','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `nim`, `phone`, `address`, `tempat_lahir`, `tanggal_lahir`, `gender`, `agama`, `status`, `profile_picture`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Atmin YGY', 'atmin@ipwija.ac.id', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ACTIVE', NULL, NULL, '$2y$12$NRSWB6uZ7Fsc3Mv/ILtcD.ClwBlme7x6MG6wWhtchjrKDHF1mbefq', NULL, '2026-01-13 23:44:23', '2026-01-13 23:52:55', NULL),
(2, 'User Biasa', 'user@ipwija.com', 'user', '202303110009', '088210936602', 'JL LOREM IPSUM NO.37', 'Jakarta', '2005-01-22', NULL, 'ISLAM', 'ACTIVE', NULL, NULL, '$2y$12$q3J9gBX.IeMxm92bkEcFOeceGrkVn5JC8w29IYQ9K3MuSmGa.fXr2', NULL, '2026-01-13 23:44:23', '2026-01-14 00:51:20', NULL),
(3, 'Stevanus Andika Galih Setiawan', 'stevanusstudent@gmail.com', 'user', '20303110008', '089604134028', 'JL SEPAKAT 4 NO.57 RT003 RW 001 CILANGKAP JAKARTA TIMUR,13870', 'Jakarta', '2005-02-06', NULL, 'KRISTEN', 'ACTIVE', 'user_photos/1768380783_3.jpg', NULL, '$2y$12$8FrpUzxxe8d/bU1b5hkmou.r7uFuSy64XkVvbEy0X/wsWtqC6ds5u', NULL, '2026-01-14 01:43:07', '2026-01-14 01:53:05', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `books_slug_unique` (`slug`),
  ADD UNIQUE KEY `books_isbn_unique` (`isbn`),
  ADD KEY `books_category_id_foreign` (`category_id`);

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `borrowings_user_id_foreign` (`user_id`),
  ADD KEY `borrowings_book_id_foreign` (`book_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `fines`
--
ALTER TABLE `fines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fines_borrowing_id_foreign` (`borrowing_id`),
  ADD KEY `fines_user_id_foreign` (`user_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `password_reset_tokens_verification_code_unique` (`verification_code`),
  ADD KEY `password_reset_tokens_email_verification_code_index` (`email`,`verification_code`),
  ADD KEY `password_reset_tokens_email_is_used_index` (`email`,`is_used`),
  ADD KEY `password_reset_tokens_expires_at_index` (`expires_at`),
  ADD KEY `password_reset_tokens_email_index` (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_nim_unique` (`nim`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fines`
--
ALTER TABLE `fines`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrowings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fines`
--
ALTER TABLE `fines`
  ADD CONSTRAINT `fines_borrowing_id_foreign` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fines_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
