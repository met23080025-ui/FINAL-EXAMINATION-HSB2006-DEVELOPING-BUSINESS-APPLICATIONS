-- =============================================================================
-- database/schema.sql
-- Golden Lotus Restaurant Reservation System — HSB2006, Class MET4
-- Phase P3 deliverable. Matches docs/data-dictionary.md exactly (see that
-- file for the full column-by-column rationale). Target engine: MySQL 8 /
-- MariaDB (XAMPP). Character set utf8mb4_unicode_ci throughout.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS golden_lotus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE golden_lotus;

-- Idempotent re-import: drop tables in FK-safe order, disabling FK checks
-- while doing so (needed because reservations references the other three).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS `tables`;
DROP TABLE IF EXISTS time_slots;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- users
-- Vietnamese: Bảng tài khoản người dùng, dùng chung cho khách hàng và quản trị
-- viên, phân biệt qua cột `role`. Mật khẩu chỉ lưu dạng băm (hash) bcrypt do
-- PHP password_hash() sinh ra — không bao giờ lưu plaintext.
-- -----------------------------------------------------------------------------
CREATE TABLE users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name       VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  phone           VARCHAR(20)  NULL,
  role            ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tai khoan khach hang va quan tri vien';

-- -----------------------------------------------------------------------------
-- tables
-- Vietnamese: Bảng danh sách bàn ăn của nhà hàng (20 bàn, 4 khu vực cố định).
-- `area` dùng ENUM thay vì bảng tra cứu riêng vì 4 khu vực là tham số nghiệp vụ
-- đã khoá (locked) trong CLAUDE.md và không dự kiến thay đổi trong phạm vi đồ
-- án — xem thêm ghi chú thiết kế trong docs/data-dictionary.md.
-- -----------------------------------------------------------------------------
CREATE TABLE `tables` (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  table_code      VARCHAR(10)  NOT NULL,
  capacity        TINYINT UNSIGNED NOT NULL,
  area            ENUM('indoor_main','terrace','garden','vip') NOT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tables_code (table_code),
  -- Vietnamese: rang buoc dam bao suc chua luon duong; MySQL 8.0.16+/MariaDB
  -- 10.2.1+ thuc thi CHECK nay; ban PHP van validate lai o tang ung dung
  -- (khong bao gio chi tin tuong CSDL hoac client).
  CONSTRAINT chk_tables_capacity CHECK (capacity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Danh sach ban an theo khu vuc';

-- -----------------------------------------------------------------------------
-- time_slots
-- Vietnamese: 7 khung gio phuc vu co dinh moi ngay (90 phut/khung), admin co
-- the bat/tat tung khung qua is_active nhung khong xoa (giu lich su dat cho).
-- -----------------------------------------------------------------------------
CREATE TABLE time_slots (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  start_time      TIME         NOT NULL,
  end_time        TIME         NOT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT chk_time_slots_order CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Khung gio phuc vu co dinh (90 phut moi khung)';

-- -----------------------------------------------------------------------------
-- reservations
-- Vietnamese: Bang dat cho — trung tam cua toan bo he thong.
--
-- RANG BUOC CHONG TRUNG LICH (double-booking constraint) — giai thich chi tiet:
--
-- Yeu cau nghiep vu: mot ban (table_id) chi duoc giu TOI DA MOT don dat cho
-- "con hieu luc" (chua bi huy/tu choi) cho moi cap (reservation_date,
-- time_slot_id). Neu chi dat UNIQUE truc tiep tren (table_id,
-- reservation_date, time_slot_id), thi mot don da bi 'cancelled' hoac
-- 'rejected' van chiem mot dong trong index do -> KHONG THE dat lai ban do
-- cho cung ngay/khung gio, sai voi nghiep vu thuc te (khach huy thi ban phai
-- duoc mo lai cho khach khac).
--
-- Giai phap da chon: THEM MOT COT SINH (generated column) `active_slot_key`.
-- Cot nay tra ve NULL khi status la 'cancelled' hoac 'rejected', va tra ve
-- mot chuoi duy nhat (table_id + reservation_date + time_slot_id) cho moi
-- trang thai con lai (pending/confirmed/completed/no_show). Dat UNIQUE INDEX
-- tren chinh cot sinh nay. Vi MySQL/MariaDB coi moi gia tri NULL la "khac
-- nhau" trong UNIQUE INDEX (khong xung dot voi nhau), cac don da huy/tu choi
-- khong con chan viec dat lai ban, trong khi cac don con hieu luc van bi CSDL
-- chan trung ngay tai thoi diem INSERT — dam bao TINH NGUYEN TU (atomicity),
-- khong co race condition giua "kiem tra" va "ghi".
--
-- Uu diem: an toan tuyet doi duoi tai dong thoi cao (hai request INSERT gan
-- nhu cung luc cho cung ban/ngay/khung gio — request thu hai se bi CSDL tu
-- choi ngay, khong phu thuoc vao logic PHP kiem tra truoc). Don gian hon
-- trigger, khong can code thu tuc luu tru (stored procedure) rieng.
--
-- Nhuoc diem / danh doi: cot sinh (STORED) ton them mot chut dung luong luu
-- tru vi gia tri duoc tinh san va ghi xuong dia (thay vi VIRTUAL). Phai luon
-- nho rang UNIQUE INDEX nay chi bao ve o muc CSDL — logic PHP van phai kiem
-- tra truoc khi INSERT de tra loi thong bao loi than thien cho nguoi dung
-- (UNIQUE INDEX chi la lop phong ve cuoi cung, khong thay the validation).
--
-- Phuong an da can nhac nhung khong chon: dung TRIGGER BEFORE INSERT de
-- SELECT kiem tra trung roi moi cho INSERT tiep tuc. Bi loai vi buoc
-- "kiem tra" va "ghi" trong trigger khong nguyen tu voi nhau neu khong khoa
-- (lock) them bang tay — duoi tai dong thoi cao van co the xay ra race
-- condition (hai transaction cung SELECT thay "chua trung" roi cung INSERT).
-- Cot sinh + UNIQUE INDEX an toan hon vi ban than co che UNIQUE INDEX cua
-- InnoDB da nguyen tu o muc storage engine.
-- -----------------------------------------------------------------------------
CREATE TABLE reservations (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           INT UNSIGNED NOT NULL,
  table_id          INT UNSIGNED NOT NULL,
  time_slot_id      INT UNSIGNED NOT NULL,
  reservation_date  DATE         NOT NULL,
  party_size        TINYINT UNSIGNED NOT NULL,
  notes             VARCHAR(255) NULL,
  status            ENUM('pending','confirmed','completed','no_show','cancelled','rejected')
                     NOT NULL DEFAULT 'pending',
  actioned_by       INT UNSIGNED NULL,
  actioned_at       DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Vietnamese: cot sinh dung cho rang buoc chong trung lich, xem giai thich o tren.
  active_slot_key   VARCHAR(50) GENERATED ALWAYS AS (
                       CASE WHEN status IN ('cancelled','rejected') THEN NULL
                            ELSE CONCAT(table_id, '_', reservation_date, '_', time_slot_id)
                       END
                     ) STORED,
  PRIMARY KEY (id),
  -- Vietnamese: day chinh la rang buoc chong dat trung ban (double-booking).
  UNIQUE KEY uq_reservations_active_slot (active_slot_key),
  KEY idx_reservations_date (reservation_date),
  KEY idx_reservations_status (status),
  CONSTRAINT fk_reservations_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reservations_table
    FOREIGN KEY (table_id) REFERENCES `tables`(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reservations_slot
    FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reservations_actioned_by
    FOREIGN KEY (actioned_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  -- Vietnamese: dam bao so khach luon duong; nhu tren, PHP van validate lai
  -- ca party_size > 0 lan party_size <= tables.capacity o tang ung dung.
  CONSTRAINT chk_reservations_party_size CHECK (party_size > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Don dat cho - trung tam nghiep vu cua he thong';
