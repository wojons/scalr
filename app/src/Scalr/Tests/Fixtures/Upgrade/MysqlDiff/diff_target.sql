CREATE TABLE `bar` (
  `idbar` int(11) NULL,
  `barcol` int(11) DEFAULT NULL,
  `barcol1` int(11)  NOT NULL DEFAULT '0',
  PRIMARY KEY (`idbar`),
  KEY `fk_bar_1_idx` (`barcol`),
  KEY `fk_bar_2_idx` (`barcol1`),
  CONSTRAINT `fk_bar_1` FOREIGN KEY (`barcol`) REFERENCES `foo` (`idfoo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `foo` (
  `idfoo` int(11) NOT NULL,
  `foocol` varchar(45) DEFAULT NULL,
  `foocol2` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idfoo`),
  KEY `test_ix` (`foocol`,`foocol2`(2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;