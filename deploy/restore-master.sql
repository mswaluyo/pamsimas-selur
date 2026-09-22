INSERT INTO `pumps` (`id`, `pump_name`, `flow_rate_lps`, `power_hp`, `power_watt`, `delay_seconds`, `on_duration_seconds`, `off_duration_seconds`) VALUES
(1, 'Pompa Ngasinan', 1, 4, 1600, 40, 480, 600),
(2, 'Pompa Mbaran', 0.6, 1.5, 1300, 20, 660, 600),
(3, 'Pompa Danyangan', 0.45, 2, 2400, 185, 480, 900),
(4, 'Pompa Kendal', 0.4, 1, 1300, 30, 1800, 600);

INSERT INTO `sensors` (`id`, `sensor_name`, `sensor_type`, `full_tank_distance`, `trigger_percentage`, `created_at`) VALUES
(1, 'Sensor Danyangan', 'HC-SR04', 30, 70, '2025-11-20 16:00:00'),
(2, 'Sensor Mbaran', 'HC-SR04', 25, 80, '2026-05-15 07:41:54');

INSERT INTO `tank_configurations` (`id`, `tank_name`, `tank_shape`, `height`, `rectangular_dim_id`, `circular_dim_id`) VALUES
(1, 'Pamsimas Ngasinan', 'kotak', 400, 1, NULL),
(2, 'Pamsimas Mbaran', 'kotak', 225, 2, NULL),
(3, 'Pamsimas Danyangan', 'bulat', 120, NULL, 1);

INSERT INTO `tank_circular_dimensions` (`id`, `diameter`) VALUES
(1, 200);

INSERT INTO `tank_rectangular_dimensions` (`id`, `length`, `width`) VALUES
(1, 400, 400),
(2, 300, 300);

