UPDATE phyxo_user_infos SET language = 'en_GB';
DELETE FROM phyxo_config where param = 'key_comment_valid_time';
INSERT INTO phyxo_config (param,value) VALUES('key_comment_valid_time', 0);
