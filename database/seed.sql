-- =============================================================================
-- database/seed.sql
-- Golden Lotus Restaurant Reservation System — HSB2006, Class MET4
-- Phase P3 deliverable. Import AFTER database/schema.sql.
--
-- Vietnamese: Du lieu mau (seed data) de demo he thong: 20 ban / 4 khu vuc,
-- 7 khung gio/ngay, 1 tai khoan admin + 6 tai khoan khach hang, va ~55 luot
-- dat cho trai dai tu 14 ngay truoc den 7 ngay sau ngay import (tinh tuong
-- doi qua CURDATE(), khong ghi ngay co dinh, de du lieu demo luon "con moi"
-- du import lai bat ky ngay nao trong qua trinh lam do an).
--
-- Mat khau seed dung chung cho ca 7 tai khoan: Password123!
-- Hash bcrypt duoi day duoc sinh THAT (khong phai chuoi gia), voi tien to
-- $2y$ - dung tien to chinh tac ma PHP password_hash() tu sinh ra, de dam
-- bao tuong thich 100% voi password_verify() tren moi phien ban PHP co ham
-- nay (khong dung tien to $2b$ vi chua co moi truong PHP that de kiem chung
-- truc tiep trong qua trinh lam seed nay).
-- =============================================================================

USE golden_lotus;

-- -----------------------------------------------------------------------------
-- users: 1 admin + 6 customer
-- -----------------------------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, phone, role, is_active) VALUES
  ('Golden Lotus Admin', 'admin@goldenlotus.test',     '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000001', 'admin',    1),
  ('Nguyen Van An',      'customer1@goldenlotus.test', '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000002', 'customer', 1),
  ('Tran Thi Binh',      'customer2@goldenlotus.test', '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000003', 'customer', 1),
  ('Le Van Cuong',       'customer3@goldenlotus.test', '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000004', 'customer', 1),
  ('Pham Thi Dung',      'customer4@goldenlotus.test', '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000005', 'customer', 1),
  ('Hoang Van Em',       'customer5@goldenlotus.test', '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000006', 'customer', 1),
  ('Vu Thi Giang',       'customer6@goldenlotus.test', '$2y$10$3VobGIvFb7EHdjTj9ACDn.jXcomudurtVvdtViGXBzNX0cGsy9h4m', '0900000007', 'customer', 1);
-- id: 1=admin, 2=customer1, 3=customer2, 4=customer3, 5=customer4, 6=customer5, 7=customer6

-- -----------------------------------------------------------------------------
-- tables: 20 ban dung nhu khoa trong CLAUDE.md
-- -----------------------------------------------------------------------------
INSERT INTO `tables` (table_code, capacity, area) VALUES
  ('T01', 2,  'indoor_main'),
  ('T02', 2,  'indoor_main'),
  ('T03', 2,  'indoor_main'),
  ('T04', 2,  'indoor_main'),
  ('T05', 4,  'indoor_main'),
  ('T06', 4,  'indoor_main'),
  ('T07', 4,  'indoor_main'),
  ('T08', 4,  'indoor_main'),
  ('T09', 4,  'terrace'),
  ('T10', 4,  'terrace'),
  ('T11', 4,  'terrace'),
  ('T12', 6,  'terrace'),
  ('T13', 6,  'terrace'),
  ('T14', 6,  'garden'),
  ('T15', 6,  'garden'),
  ('T16', 8,  'garden'),
  ('T17', 8,  'garden'),
  ('V01', 8,  'vip'),
  ('V02', 8,  'vip'),
  ('V03', 12, 'vip');
-- id 1..20 in the order above (T01=1 ... T17=17, V01=18, V02=19, V03=20)

-- -----------------------------------------------------------------------------
-- time_slots: 7 khung gio co dinh, moi khung 90 phut
-- -----------------------------------------------------------------------------
INSERT INTO time_slots (start_time, end_time) VALUES
  ('11:00:00', '12:30:00'),
  ('12:30:00', '14:00:00'),
  ('14:00:00', '15:30:00'),
  ('15:30:00', '17:00:00'),
  ('17:00:00', '18:30:00'),
  ('18:30:00', '20:00:00'),
  ('20:00:00', '21:30:00');
-- id 1..7 in the order above

-- -----------------------------------------------------------------------------
-- reservations: 57 luot dat cho, tu -14 ngay den +7 ngay tinh tu ngay import.
-- Phan bo trang thai: qua khu (offset < 0) chu yeu 'completed', mot so
-- 'no_show'/'cancelled'; 2 ngay toi (offset 0,1) co vai don 'pending' de hang
-- cho duyet cua admin khong bao gio rong khi demo; con lai chu yeu 'confirmed'.
-- Khung gio buoi toi (17:00-18:30, 18:30-20:00) va vai ngay duoc cho nhieu
-- luot dat hon de bao cao "khung gio dong nhat" (busiest slot) co y nghia.
-- Moi dong deu thoa capacity cua ban va khong trung (table_id, ngay, khung
-- gio) voi bat ky dong nao khac trong tap seed nay (kiem tra thu cong khi
-- sinh du lieu), nen hoan toan tuong thich voi UNIQUE INDEX active_slot_key.
-- Cot: (user_id, table_id, time_slot_id, reservation_date, party_size,
--       status, actioned_by, actioned_at)
-- -----------------------------------------------------------------------------
INSERT INTO reservations
  (user_id, table_id, time_slot_id, reservation_date, party_size, status, actioned_by, actioned_at)
VALUES
  (6, 19, 5, DATE_ADD(CURDATE(), INTERVAL -14 DAY), 6, 'completed', 1, CURRENT_TIMESTAMP),
  (3, 4, 5, DATE_ADD(CURDATE(), INTERVAL -14 DAY), 1, 'completed', 1, CURRENT_TIMESTAMP),
  (4, 15, 7, DATE_ADD(CURDATE(), INTERVAL -13 DAY), 5, 'completed', 1, CURRENT_TIMESTAMP),
  (7, 18, 2, DATE_ADD(CURDATE(), INTERVAL -13 DAY), 7, 'completed', 1, CURRENT_TIMESTAMP),
  (6, 17, 3, DATE_ADD(CURDATE(), INTERVAL -12 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 3, 6, DATE_ADD(CURDATE(), INTERVAL -12 DAY), 1, 'cancelled', NULL, NULL),
  (3, 11, 3, DATE_ADD(CURDATE(), INTERVAL -11 DAY), 4, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 12, 1, DATE_ADD(CURDATE(), INTERVAL -11 DAY), 4, 'completed', 1, CURRENT_TIMESTAMP),
  (7, 17, 6, DATE_ADD(CURDATE(), INTERVAL -10 DAY), 3, 'no_show', 1, CURRENT_TIMESTAMP),
  (3, 3, 7, DATE_ADD(CURDATE(), INTERVAL -10 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (7, 1, 5, DATE_ADD(CURDATE(), INTERVAL -9 DAY), 1, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 13, 4, DATE_ADD(CURDATE(), INTERVAL -9 DAY), 5, 'completed', 1, CURRENT_TIMESTAMP),
  (3, 13, 5, DATE_ADD(CURDATE(), INTERVAL -9 DAY), 2, 'no_show', 1, CURRENT_TIMESTAMP),
  (6, 20, 7, DATE_ADD(CURDATE(), INTERVAL -8 DAY), 4, 'completed', 1, CURRENT_TIMESTAMP),
  (2, 2, 5, DATE_ADD(CURDATE(), INTERVAL -8 DAY), 1, 'completed', 1, CURRENT_TIMESTAMP),
  (4, 20, 5, DATE_ADD(CURDATE(), INTERVAL -8 DAY), 6, 'completed', 1, CURRENT_TIMESTAMP),
  (6, 9, 5, DATE_ADD(CURDATE(), INTERVAL -7 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 4, 6, DATE_ADD(CURDATE(), INTERVAL -7 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (3, 10, 5, DATE_ADD(CURDATE(), INTERVAL -6 DAY), 3, 'no_show', 1, CURRENT_TIMESTAMP),
  (7, 11, 4, DATE_ADD(CURDATE(), INTERVAL -6 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 2, 5, DATE_ADD(CURDATE(), INTERVAL -5 DAY), 2, 'no_show', 1, CURRENT_TIMESTAMP),
  (6, 2, 2, DATE_ADD(CURDATE(), INTERVAL -5 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (4, 13, 6, DATE_ADD(CURDATE(), INTERVAL -4 DAY), 6, 'completed', 1, CURRENT_TIMESTAMP),
  (4, 2, 5, DATE_ADD(CURDATE(), INTERVAL -4 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 2, 5, DATE_ADD(CURDATE(), INTERVAL -3 DAY), 1, 'no_show', 1, CURRENT_TIMESTAMP),
  (7, 1, 7, DATE_ADD(CURDATE(), INTERVAL -3 DAY), 2, 'completed', 1, CURRENT_TIMESTAMP),
  (5, 11, 5, DATE_ADD(CURDATE(), INTERVAL -2 DAY), 3, 'completed', 1, CURRENT_TIMESTAMP),
  (7, 2, 3, DATE_ADD(CURDATE(), INTERVAL -2 DAY), 1, 'completed', 1, CURRENT_TIMESTAMP),
  (6, 15, 3, DATE_ADD(CURDATE(), INTERVAL -2 DAY), 3, 'completed', 1, CURRENT_TIMESTAMP),
  (6, 2, 3, DATE_ADD(CURDATE(), INTERVAL -1 DAY), 2, 'cancelled', NULL, NULL),
  (3, 16, 3, DATE_ADD(CURDATE(), INTERVAL -1 DAY), 8, 'completed', 1, CURRENT_TIMESTAMP),
  (3, 4, 3, DATE_ADD(CURDATE(), INTERVAL -1 DAY), 1, 'completed', 1, CURRENT_TIMESTAMP),
  (4, 2, 3, DATE_ADD(CURDATE(), INTERVAL 0 DAY), 1, 'confirmed', 1, CURRENT_TIMESTAMP),
  (2, 1, 6, DATE_ADD(CURDATE(), INTERVAL 0 DAY), 2, 'pending', NULL, NULL),
  (4, 16, 3, DATE_ADD(CURDATE(), INTERVAL 0 DAY), 4, 'confirmed', 1, CURRENT_TIMESTAMP),
  (6, 14, 5, DATE_ADD(CURDATE(), INTERVAL 0 DAY), 5, 'confirmed', 1, CURRENT_TIMESTAMP),
  (7, 13, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 5, 'pending', NULL, NULL),
  (5, 2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 'pending', NULL, NULL),
  (7, 17, 7, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 7, 'confirmed', 1, CURRENT_TIMESTAMP),
  (4, 9, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 'pending', NULL, NULL),
  (5, 17, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 6, 'pending', NULL, NULL),
  (2, 12, 6, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 3, 'confirmed', 1, CURRENT_TIMESTAMP),
  (2, 4, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 2, 'confirmed', 1, CURRENT_TIMESTAMP),
  (2, 3, 6, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1, 'confirmed', 1, CURRENT_TIMESTAMP),
  (4, 15, 6, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 3, 'confirmed', 1, CURRENT_TIMESTAMP),
  (5, 13, 4, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 6, 'pending', NULL, NULL),
  (4, 1, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 1, 'confirmed', 1, CURRENT_TIMESTAMP),
  (4, 15, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 5, 'confirmed', 1, CURRENT_TIMESTAMP),
  (2, 9, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 4, 'confirmed', 1, CURRENT_TIMESTAMP),
  (2, 9, 6, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 4, 'pending', NULL, NULL),
  (6, 20, 2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 7, 'pending', NULL, NULL),
  (2, 15, 5, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 6, 'confirmed', 1, CURRENT_TIMESTAMP),
  (3, 7, 5, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 3, 'confirmed', 1, CURRENT_TIMESTAMP),
  (4, 6, 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 3, 'pending', NULL, NULL),
  (2, 10, 3, DATE_ADD(CURDATE(), INTERVAL -3 DAY), 3, 'rejected', 1, CURRENT_TIMESTAMP),
  (2, 15, 5, DATE_ADD(CURDATE(), INTERVAL -4 DAY), 6, 'rejected', 1, CURRENT_TIMESTAMP),
  (5, 18, 6, DATE_ADD(CURDATE(), INTERVAL -5 DAY), 7, 'rejected', 1, CURRENT_TIMESTAMP);
