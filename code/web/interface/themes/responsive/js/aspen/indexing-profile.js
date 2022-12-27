AspenDiscovery.IndexingClass = (function () {
    return {
        indexingClassSelect2: function (id) {
            //Hide all
            $(".form-group").each(function () {
                $(this).hide();
            });

            //Show Class Select
            $("#propertyRowid").show();
            $("#propertyRowindexingClass").show();
            $(".btn-group").parent().show();


            //Config per Class
            var ilsOptions = {
                //Common for all classes
                commonFields: ['propertyRowid', 'propertyRowname', 'propertyRowmarcPath', 'propertyRowfilenamesToInclude', 'propertyRowmarcEncoding', 'propertyRowindividualMarcPath', 'propertyRownumCharsToCreateFolderFrom', 'propertyRowcreateFolderFromLeadingCharacters', 'propertyRowgroupingClass', 'propertyRowrecordDriver', 'propertyRowcatalogDriver', 'propertyRowrecordUrlComponent', 'propertyRowprocessRecordLinking', 'propertyRowrecordNumberTag', 'propertyRowrecordNumberSubfield', 'propertyRowrecordNumberPrefix', 'propertyRowcustomMarcFieldsToIndexAsKeyword', 'propertyRowtreatUnknownLanguageAs', 'propertyRowtreatUndeterminedLanguageAs', 'propertyRowsuppressRecordsWithUrlsMatching', 'propertyRowbCode3sToSuppress', 'propertyRowdetermineAudienceBy', 'propertyRowaudienceSubfield', 'propertyRowtreatUnknownAudienceAs', 'propertyRowdetermineLiteraryFormBy', 'propertyRowliteraryFormSubfield', 'propertyRowhideUnknownLiteraryForm', 'propertyRowhideNotCodedLiteraryForm', 'propertyRowitemSection', 'propertyRowsuppressItemlessBibs', 'propertyRowitemTag', 'propertyRowitemRecordNumber', 'propertyRowuseItemBasedCallNumbers', 'propertyRowcallNumberPrestamp', 'propertyRowcallNumber', 'propertyRowcallNumberCutter', 'propertyRowcallNumberPoststamp', 'propertyRowlocation', 'propertyRowincludeLocationNameInDetailedLocation', 'propertyRownonHoldableLocations', 'propertyRowlocationsToSuppress', 'propertyRowsubLocation', 'propertyRowshelvingLocation', 'propertyRowcollection', 'propertyRowcollectionsToSuppress', 'propertyRowvolume', 'propertyRowitemUrl', 'propertyRowbarcode', 'propertyRowstatus', 'propertyRownonHoldableStatuses', 'propertyRowstatusesToSuppress', 'propertyRowtreatLibraryUseOnlyGroupedStatusesAsAvailable', 'propertyRowtotalCheckouts', 'propertyRowlastYearCheckouts', 'propertyRowyearToDateCheckouts', 'propertyRowtotalRenewals', 'propertyRowiType', 'propertyRownonHoldableITypes', 'propertyRowiTypesToSuppress', 'propertyRowdueDate', 'propertyRowdueDateFormat', 'propertyRowdateCreated', 'propertyRowdateCreatedFormat', 'propertyRowlastCheckinDate', 'propertyRowlastCheckinFormat', 'propertyRowiCode2', 'propertyRowuseICode2Suppression', 'propertyRowiCode2sToSuppress', 'propertyRowformat', 'propertyRoweContentDescriptor', 'propertyRowdoAutomaticEcontentSuppression', 'propertyRownoteSubfield', 'propertyRowformatMappingSection', 'propertyRowformatSource', 'propertyRowfallbackFormatField', 'propertyRowspecifiedFormat', 'propertyRowspecifiedFormatCategory', 'propertyRowspecifiedFormatBoost', 'propertyRowcheckRecordForLargePrint', 'propertyRowformatMap', 'propertyRowstatusMappingSection', 'propertyRowstatusMap', 'propertyRoworderSection', 'propertyRoworderTag', 'propertyRoworderStatus', 'propertyRoworderLocationSingle', 'propertyRoworderLocation', 'propertyRoworderCopies', 'propertyRoworderCode3', 'propertyRowregroupAllRecords', 'propertyRowrunFullUpdate', 'propertyRowlastUpdateOfChangedRecords', 'propertyRowlastUpdateOfAllRecords', 'propertyRowlastChangeProcessed', 'propertyRowfullMarcExportRecordIdThreshold', 'propertyRowlastUpdateFromMarcExport', 'propertyRowlastVolumeExportTimestamp', 'propertyRowlastUpdateOfAuthorities', 'propertyRowtranslationMaps', 'FloatingSave'],
                //Specific per class
                koha: ['propertyRowname', 'propertyRowmarcPath'],
                evolve: ['propertyRowfilenamesToInclude', 'e2a'],
                arlingtonkoha: ['ak1', 'ak2'],
                carlx: ['c1', 'c2'],
                iii: ['i1', 'i2'],
                sideloadedecontent: ['s1', 's2'],
                symphony: ['sy1', 'sy2'],
                polaris: ['p1', 'p2'],
                evergreen: ['ev1', 'ev2']
            };

            //Show rows for selected class
            var iterator = ilsOptions[$("#indexingClassSelect").val()];
            iterator.concat(ilsOptions['commonFields']);
            iterator.forEach(function (value) {
                $("#" + value).show();
            });
        }
    }
}(AspenDiscovery.IndexingClass || {}));
