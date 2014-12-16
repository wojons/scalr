CREATE TABLE `bar` (
  `idbar` int(11) NOT NULL,
  `barcol` int(11) DEFAULT NULL,
  `barcol1` int(11) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`idbar`),
  KEY `fk_bar_1_idx` (`barcol`),
  KEY `fk_bar_2_idx` (`barcol1`),
  CONSTRAINT `fk_bar_2` FOREIGN KEY (`barcol1`) REFERENCES `foobar` (`foobarcol2`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_bar_1` FOREIGN KEY (`barcol`) REFERENCES `foo` (`idfoo`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `foo` (
  `idfoo` int(11) NOT NULL,
  `foocol` varchar(45) DEFAULT NULL,
  `foocol1` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `foocol2` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idfoo`),
  UNIQUE KEY `foocol1_UNIQUE` (`foocol1`),
  KEY `test_ix` (`foocol`(3),`foocol2`(3))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `foobar` (
  `idfoobar` int(11) NOT NULL,
  `foobarcol` varchar(45) DEFAULT NULL,
  `foobarcol1` varchar(45) DEFAULT NULL,
  `foobarcol2` int(11) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`idfoobar`),
  FULLTEXT KEY `index2` (`foobarcol`,`foobarcol1`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;