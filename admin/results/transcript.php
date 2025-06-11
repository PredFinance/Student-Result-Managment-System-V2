                    <p class="mb-1"><?php echo htmlspecialchars($institution['address'] ?? ''); ?></p>
                    <p class="mb-3"><strong>OFFICIAL TRANSCRIPT</strong></p>
                </div>
                
                <!-- Student Information -->
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Student Name:</th>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Matric Number:</th>
                                <td><?php echo htmlspecialchars($student['matric_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Department:</th>
                                <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Level:</th>
                                <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Date of Birth:</th>
                                <td><?php echo $student['date_of_birth'] ? format_date($student['date_of_birth'], 'd M, Y') : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Gender:</th>
                                <td><?php echo ucfirst($student['gender'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Academic Record -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>Academic Record
                </h5>
            </div>
            
            <div class="card-body">
                <?php if (empty($transcript_data)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No academic records found for this student.
                    </div>
                <?php else: ?>
                    <?php foreach ($transcript_data as $session_name => $session_data): ?>
                        <div class="transcript-session mb-4">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-calendar me-2"></i><?php echo htmlspecialchars($session_name); ?> Academic Session
                            </h6>
                            
                            <?php foreach ($session_data as $semester_name => $semester_data): ?>
                                <div class="transcript-semester">
                                    <div class="transcript-semester-header">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($semester_name); ?> Semester</h6>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Course Code</th>
                                                    <th>Course Title</th>
                                                    <th class="text-center">Credit Units</th>
                                                    <th class="text-center">Score</th>
                                                    <th class="text-center">Grade</th>
                                                    <th class="text-center">Grade Point</th>
                                                    <th class="text-center">Remark</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $semester_total_units = 0;
                                                $semester_total_points = 0;
                                                
                                                foreach ($semester_data['results'] as $result): 
                                                    $semester_total_units += $result['credit_units'];
                                                    $semester_total_points += ($result['grade_point'] * $result['credit_units']);
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($result['course_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                                        <td class="text-center"><?php echo $result['credit_units']; ?></td>
                                                        <td class="text-center"><?php echo $result['total_score']; ?></td>
                                                        <td class="text-center"><?php echo $result['grade']; ?></td>
                                                        <td class="text-center"><?php echo $result['grade_point']; ?></td>
                                                        <td class="text-center"><?php echo $result['remark']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th colspan="2">Semester Total</th>
                                                    <th class="text-center"><?php echo $semester_total_units; ?></th>
                                                    <th colspan="2"></th>
                                                    <th class="text-center"><?php echo number_format($semester_total_points, 1); ?></th>
                                                    <th></th>
                                                </tr>
                                                <?php if ($semester_data['gpa']): ?>
                                                <tr>
                                                    <th colspan="6">Semester GPA</th>
                                                    <th class="text-center"><?php echo number_format($semester_data['gpa']['gpa'], 2); ?></th>
                                                </tr>
                                                <?php endif; ?>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Cumulative Summary -->
                    <?php if ($cgpa_info): ?>
                        <div class="transcript-summary">
                            <h6 class="mb-3">
                                <i class="bi bi-bar-chart me-2"></i>Cumulative Academic Summary
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="60%">Total Credit Units Attempted:</th>
                                            <td><?php echo $cgpa_info['total_credit_units']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Total Grade Points Earned:</th>
                                            <td><?php echo number_format($cgpa_info['total_grade_points'], 1); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Cumulative Grade Point Average (CGPA):</th>
                                            <td><strong><?php echo number_format($cgpa_info['cgpa'], 2); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="60%">Class of Degree:</th>
                                            <td>
                                                <?php 
                                                $cgpa = $cgpa_info['cgpa'];
                                                if ($cgpa >= 4.50) {
                                                    echo '<span class="badge bg-success">First Class</span>';
                                                } elseif ($cgpa >= 3.50) {
                                                    echo '<span class="badge bg-primary">Second Class Upper</span>';
                                                } elseif ($cgpa >= 2.40) {
                                                    echo '<span class="badge bg-info">Second Class Lower</span>';
                                                } elseif ($cgpa >= 1.50) {
                                                    echo '<span class="badge bg-warning">Third Class</span>';
                                                } elseif ($cgpa >= 1.00) {
                                                    echo '<span class="badge bg-secondary">Pass</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Fail</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Academic Status:</th>
                                            <td>
                                                <?php 
                                                if ($cgpa >= 1.00) {
                                                    echo '<span class="badge bg-success">Good Standing</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Academic Probation</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Last Updated:</th>
                                            <td><?php echo format_date($cgpa_info['updated_at'] ?? $cgpa_info['created_at'], 'd M, Y'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Grading Scale -->
                    <div class="mt-4">
                        <h6 class="mb-3">
                            <i class="bi bi-info-circle me-2"></i>Grading Scale
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Grade</th>
                                            <th>Score Range</th>
                                            <th>Grade Point</th>
                                            <th>Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>A</td>
                                            <td>70-100</td>
                                            <td>5.0</td>
                                            <td>Excellent</td>
                                        </tr>
                                        <tr>
                                            <td>B</td>
                                            <td>60-69</td>
                                            <td>4.0</td>
                                            <td>Very Good</td>
                                        </tr>
                                        <tr>
                                            <td>C</td>
                                            <td>50-59</td>
                                            <td>3.0</td>
                                            <td>Good</td>
                                        </tr>
                                        <tr>
                                            <td>D</td>
                                            <td>45-49</td>
                                            <td>2.0</td>
                                            <td>Fair</td>
                                        </tr>
                                        <tr>
                                            <td>E</td>
                                            <td>40-44</td>
                                            <td>1.0</td>
                                            <td>Pass</td>
                                        </tr>
                                        <tr>
                                            <td>F</td>
                                            <td>0-39</td>
                                            <td>0.0</td>
                                            <td>Fail</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>CGPA Range</th>
                                            <th>Class of Degree</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>4.50 - 5.00</td>
                                            <td>First Class</td>
                                        </tr>
                                        <tr>
                                            <td>3.50 - 4.49</td>
                                            <td>Second Class Upper</td>
                                        </tr>
                                        <tr>
                                            <td>2.40 - 3.49</td>
                                            <td>Second Class Lower</td>
                                        </tr>
                                        <tr>
                                            <td>1.50 - 2.39</td>
                                            <td>Third Class</td>
                                        </tr>
                                        <tr>
                                            <td>1.00 - 1.49</td>
                                            <td>Pass</td>
                                        </tr>
                                        <tr>
                                            <td>0.00 - 0.99</td>
                                            <td>Fail</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Certification -->
                    <div class="mt-5 pt-4 border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Date of Issue:</strong> <?php echo date('d M, Y'); ?></p>
                                <p class="mb-1"><strong>Issued By:</strong> <?php echo htmlspecialchars($institution['institution_name']); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="mt-4">
                                    <div style="border-top: 1px solid #000; width: 200px; margin-left: auto;"></div>
                                    <p class="mb-0 mt-2"><strong>Registrar's Signature</strong></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <p class="text-muted small">
                                This is an official transcript issued by <?php echo htmlspecialchars($institution['institution_name']); ?>. 
                                Any alteration or forgery of this document is a criminal offense.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
    @media print {
        .no-print {
            display: none !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .card-header {
            background-color: transparent !important;
            border-bottom: 1px solid #000 !important;
        }
        
        .transcript-header {
            page-break-inside: avoid;
        }
        
        .transcript-session {
            page-break-inside: avoid;
        }
        
        .transcript-semester {
            page-break-inside: avoid;
        }
        
        .transcript-summary {
            page-break-inside: avoid;
        }
        
        body {
            font-size: 12px;
        }
        
        .table {
            font-size: 11px;
        }
        
        .badge {
            color: #000 !important;
            background-color: transparent !important;
            border: 1px solid #000 !important;
        }
    }
    </style>
    
    <?php include_once '../includes/footer.php'; ?>
    
<?php?>