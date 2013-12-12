Ext.define('Scalr.ui.db.backup.Calendar', {
    extend: 'Ext.Component',
    alias: 'widget.db.backup.calendar',

    cachedBackups: {},
    backups: {},

    getCell: function (calendarData) {
        var me = this,
            cell = {'date': null, 'backups': null, 'status': 'scalr-ui-dbbackups-not-this-month'};

        calendarData.cellCounter++;

        if (calendarData.cellCounter > calendarData.firstDayOfMonth && calendarData.cellCounter <= calendarData.lastDayOfMonthCell) {
            calendarData.daysCounter++;
            cell.status = 'scalr-ui-dbbackups-this-month';
            cell.date = calendarData.daysCounter + Ext.Date.format(calendarData.currentDate, " M");
            if (cell.date in me.backups) {
                cell.backups = me.backups[cell.date];
            }
            cell.backupsNumber = cell.backups ? cell.backups.length : 0;
        }
        return cell;
    },

    prepareTplData: function (date) {
        var me = this,
            calendarData = {
                currentDate: date || new Date(),
                cellCounter: 0,
                daysCounter: 0
            };
        calendarData.today = parseInt(Ext.Date.format(calendarData.currentDate, "d"));
        calendarData.firstDayOfMonth = Ext.Date.getFirstDayOfMonth(calendarData.currentDate);
        calendarData.todayCellNumber = calendarData.today + calendarData.firstDayOfMonth;
        calendarData.todayRowNumber = Math.ceil(calendarData.todayCellNumber / 7);
        calendarData.todayCellPosition = 7 - (calendarData.todayRowNumber * 7 - calendarData.todayCellNumber);
        calendarData.daysInMonth = Ext.Date.getDaysInMonth(calendarData.currentDate);
        calendarData.rowsNumber = Math.ceil((calendarData.firstDayOfMonth + calendarData.daysInMonth) / 7);
        calendarData.cellsNumber = calendarData.rowsNumber * 7;
        calendarData.lastDayOfMonthCell = calendarData.cellsNumber - (calendarData.cellsNumber - (calendarData.firstDayOfMonth + calendarData.daysInMonth));
        calendarData.formattedCurrentDate = Ext.Date.format(calendarData.currentDate, 'M Y');

        me.cachedBackups[calendarData.formattedCurrentDate] = me.backups;
        me.data = [];

        for (var i = 0; i < calendarData.rowsNumber; i++) {
            var row = [];
            for (var j = 0; j <= 6; j++) {
                row[j] = me.getCell(calendarData);
            }
            me.data[i] = row;
        }
        if (calendarData.formattedCurrentDate === Ext.Date.format(new Date(), 'M Y')) {
            me.data[calendarData.todayRowNumber - 1][calendarData.todayCellPosition - 1].status = 'scalr-ui-dbbackups-today';
        }
    },

    tpl: [
        '<div class="scalr-ui-dbbackups-calendar">',
            '<div class="scalr-ui-dbbackups-title">',
                '<div class="scalr-ui-dbbackups-cell">Sunday</div>',
                '<div class="scalr-ui-dbbackups-cell">Monday</div>',
                '<div class="scalr-ui-dbbackups-cell">Tuesday</div>',
                '<div class="scalr-ui-dbbackups-cell">Wednesday</div>',
                '<div class="scalr-ui-dbbackups-cell">Thursday</div>',
                '<div class="scalr-ui-dbbackups-cell">Friday</div>',
                '<div class="scalr-ui-dbbackups-cell">Saturday</div>',
            '</div>',
            '<tpl for=".">',
                '<div class="scalr-ui-dbbackups-row">',
                    '<tpl for=".">',
                        '<div class="scalr-ui-dbbackups-cell {status}">',
                            '<div class="scalr-ui-dbbackups-date">{date}</div>',
                            '<tpl if="backupsNumber">',
                                '<div class="scalr-ui-dbbackups-backups">',
                                    '<tpl for="backups">',
                                        '<tpl if="!this.farmId || farmId === this.farmId">',
                                            '<div><a class="scalr-ui-dbbackups-backups-link" href="#/db/backups/details?backupId={backupId}"><span><span>{time}</span> {farmName} ({serviceName})</span></a></div>',
                                        '</tpl>',
                                    '</tpl>',
                                '</div>',
                                '<div class="scalr-ui-dbbackups-show-more"><a class="scalr-ui-dbbackups-show-more-link"></a></div>',
                            '</tpl>',
                        '</div>',
                    '</tpl>',
                '</div>',
            '</tpl>',
            '<div class="x-tip scalr-ui-dbbackups-show-all-backups-container"></div>',
            '<div class="scalr-ui-dbbackups-anchor-left"></div>',
            '<div class="scalr-ui-dbbackups-anchor-right"></div>',
        '</div>'
    ],

    setRowHeight: function () {
        var me = this;
        me.calendarHeight = me.getSize().height;
        me.titleHeight = 30;
        var minCellHeight = 85,
            rowsEls = me.el.query('.scalr-ui-dbbackups-row'),
            rowsNumber = rowsEls.length,
            rowHeight = (me.calendarHeight - me.titleHeight) / rowsNumber,
            title = me.el.down('.scalr-ui-dbbackups-title');

        title.setHeight(me.titleHeight);

        if (me.calendarHeight > minCellHeight * rowsNumber + me.titleHeight) {
            Ext.each(rowsEls, function (row) {
                me.rowHeight = rowHeight;
                Ext.get(row).setHeight(me.rowHeight);
            });
        } else {
            Ext.each(rowsEls, function (row) {
                me.rowHeight = minCellHeight;
                Ext.get(row).setHeight(me.rowHeight);
            });
        }
    },

    setCellWidth: function () {
        var me = this,
            cells = me.el.query('.scalr-ui-dbbackups-cell');

        Ext.each(cells, function (cell) {
            Ext.get(cell).setWidth(100 / 7 + '%');
        });
    },

    displayBackups: function () {
        var me = this,
            backupsContainers = me.el.query('.scalr-ui-dbbackups-backups'),
            showMoreContainers = me.el.query('.scalr-ui-dbbackups-show-more'),
            rowHeight = me.rowHeight,
            dateHeight = 27,
            backupRecordHeight = 27,
            showMoreContainerHeight = 27,
            displayedBackupRecordsNumber = Math.floor((rowHeight - dateHeight - showMoreContainerHeight) / backupRecordHeight),
            backupContainerHeight = displayedBackupRecordsNumber * backupRecordHeight,
            newShowMoreContainerHeight = rowHeight - backupContainerHeight - dateHeight;

        Ext.each(backupsContainers, function (backupContainerEl, i) {
            var backupRecordsNumber = backupContainerEl.childElementCount,
                backupContainer = Ext.get(backupContainerEl),
                showMoreContainer = Ext.get(showMoreContainers[i]);

            if (backupRecordsNumber === displayedBackupRecordsNumber + 1) {
                backupContainer.setHeight(backupContainerHeight + backupRecordHeight);
                showMoreContainer.hide();
            } else if (displayedBackupRecordsNumber < backupRecordsNumber) {
                backupContainer.setHeight(backupContainerHeight);
                showMoreContainer.show();
                showMoreContainer.setHeight(newShowMoreContainerHeight);
            } else {
                backupContainer.setHeight(backupRecordsNumber * backupRecordHeight);
                showMoreContainer.hide();
            }
        });
    },

    showAllBackupsRecords: function (showMoreContainer, tooltip) {
        var me = this;
        me.hideTooltip();
        var cell = showMoreContainer.parent(),
            date = cell.down('.scalr-ui-dbbackups-date', true).innerHTML,
            backups = me.backups[date],
            records = backups.map(function (backup) {
                return '<div><a class="scalr-ui-dbbackups-backups-link" href="#/db/backups/details?backupId=' + backup.backupId + '"><span><span>' +
                    backup.time + '</span> ' + backup.farmName + ' (' + backup.serviceName + ')</span></a></div>';
            }),
            tooltipContent = '<div class="scalr-ui-dbbackups-show-all-backups-container-content">' + records.join('') + '</div>';

        tooltip.setHeight('auto');
        tooltip.setHTML(tooltipContent);

        var tooltipContentEl = tooltip.child('.scalr-ui-dbbackups-show-all-backups-container-content'),
            tooltipContentWidth = tooltipContentEl.getWidth(),
            tooltipContentMaxWidth = 300,
            tooltipContentPaddingLeft = 50;

        if (tooltipContentWidth + tooltipContentPaddingLeft > tooltipContentMaxWidth) {
            tooltipContentEl.setWidth(tooltipContentMaxWidth);
        } else {
            tooltipContentEl.setWidth(tooltipContentWidth + tooltipContentPaddingLeft);
        }

        var tooltipWidth = tooltip.getWidth(),
            tooltipHeight = tooltip.getHeight(),
            tooltipMargin = 7,
            bodyHeight = Ext.getBody().getSize().height,
            deltaY = bodyHeight - me.calendarHeight,
            cellHeight = me.rowHeight,
            cellWidth = cell.getWidth(),
            cellX = cell.getX(),
            cellY = cell.getY(),
            centralX = me.getSize().width / 2,
            showMoreContainerHeight = showMoreContainer.getHeight(),
            showMoreContainerY = showMoreContainer.getY();

        var getXPosition = function () {
            var tooltipX;
            if (cellX < centralX) {
                tooltipX = cellX + cellWidth + tooltipMargin;
            } else {
                tooltipX = cellX - tooltipWidth - tooltipMargin;
            }
            return tooltipX;
        };

        var getYPosition = function () {
            var tooltipY;
            if (tooltipHeight >= me.calendarHeight) {
                tooltip.setHeight(me.calendarHeight - me.titleHeight - tooltipMargin * 2);
                tooltipY = deltaY;
            } else {
                tooltip.setHeight(tooltipHeight);
                tooltipY = cellY - ((tooltipHeight - cellHeight) / 2);
            }
            return checkOverflow(tooltipY);
        };

        var checkOverflow = function (tooltipY) {
            var tooltipYmin = deltaY + me.titleHeight + tooltipMargin;
            if (tooltipY < tooltipYmin) {
                tooltipY = tooltipYmin;
            } else if (tooltipY + tooltipHeight + tooltipMargin > bodyHeight) {
                tooltipY = bodyHeight - tooltipHeight - tooltipMargin;
            }
            return tooltipY;
        };

        var tooltipX = getXPosition();
        tooltip.position('absolute', 100, tooltipX, getYPosition());

        var anchorFlag = cellX > centralX ? 'right' : 'left',
            anchorX = anchorFlag === 'left' ? tooltipX - 10 : tooltipX + tooltipWidth,
            anchorY = showMoreContainerY + (showMoreContainerHeight / 2) - 15;

        me.anchor[anchorFlag].position('absolute', 100, anchorX, anchorY);

        tooltip.show();
        me.anchor[anchorFlag].show();

        me.lastTarget = showMoreContainer.child('.scalr-ui-dbbackups-show-more-link');
    },

    checkCacheThenRefreshCalendar: function (date, farmId) {
        var me = this,
            formattedDate = Ext.Date.format(date, 'M Y');

        if (formattedDate in me.cachedBackups) {
            me.backups = me.cachedBackups[formattedDate];
            me.refreshCalendar(date, farmId);
        } else {
            me.getBackupsThenRefreshCalendar(date, farmId);
        }
    },

    refreshCalendar: function (date, farmId) {
        var me = this;
        me.tpl.farmId = farmId;
        me.prepareTplData(date);
        me.update(me.data);

        me.tooltip = me.el.down('.scalr-ui-dbbackups-show-all-backups-container');
        me.anchor = {
            left: me.el.down('.scalr-ui-dbbackups-anchor-left'),
            right: me.el.down('.scalr-ui-dbbackups-anchor-right')
        };

        me.setRowHeight();
        me.setCellWidth();
        me.displayBackups();
        me.hideTooltip();
    },

    getBackupsThenRefreshCalendar: function (date, farmId) {
        var me = this;
        Scalr.Request({
            url: '/db/backups/xGetListBackups',
            processBox: {
                type: 'action'
            },
            params: {
                time: date
            },

            success: function (data) {
                if (data && data['backups'] && !me.isDestroyed) {
                    me.backups = data['backups'];
                    me.refreshCalendar(date, farmId);
                }
            }
        });
    },

    beforeRender: function () {
        var me = this;
        me.callParent();
        me.prepareTplData();
        me.update(me.data);
    },

    afterRender: function () {
        var me = this;
        me.callParent();
        me.tooltip = me.el.down('.scalr-ui-dbbackups-show-all-backups-container');
        me.anchor = {
            left: me.el.down('.scalr-ui-dbbackups-anchor-left'),
            right: me.el.down('.scalr-ui-dbbackups-anchor-right')
        };

        me.hideTooltip = function () {
            me.anchor.left.hide();
            me.anchor.right.hide();
            me.tooltip.hide();
        };

        me.el.on('click', function (e) {
            var target = e.getTarget('a.scalr-ui-dbbackups-show-more-link', 0, true);

            if (!target || target === me.lastTarget && me.tooltip.isVisible()) {
                me.hideTooltip();
            } else {
                me.showAllBackupsRecords(target.parent(), me.tooltip);
            }
        });

        me.setCellWidth();
    },

    onResize: function () {
        var me = this;
        me.callParent();
        me.setRowHeight();
        me.displayBackups();
        me.hideTooltip();
    }
});
