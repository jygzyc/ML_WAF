# -*-conding:utf8-*-
#%%
import os
import time
import hashlib
from flask import Flask, flash, request, redirect, url_for, send_from_directory, jsonify
from flask_script import Manager
from flask_wtf import FlaskForm
from wtforms import StringField, SubmitField, FileField
from flask_wtf.file import FileField, FileRequired, FileAllowed
from flask_migrate import Migrate, MigrateCommand
from werkzeug.utils import secure_filename
from flask_sqlalchemy import SQLAlchemy
from pypinyin import lazy_pinyin
from flask_uploads import UploadSet, configure_uploads, patch_request_class
from sklearn.externals import joblib

import decate #import the machine learning function

#%%

basedir = os.path.abspath(os.path.dirname(__file__))

#%%
ALLOWED_EXTENSIONS = set(['php', 'png', 'jpg', 'jpeg', 'gif', 'jsp', 'asp'])
UPLOADED_WEBSHELL_DSET = os.getcwd() + "/uploads/"
UPLOADED_WEBSHELL_DSET
SECRET_KEY = "Th1s_is_SeCret"
SQLALCHEMY_DATABASE_URI = 'sqlite:///' + os.path.join(basedir, "webshell.sqlite")
SQLALCHEMY_DATABASE_URI
SQLALCHEMY_COMMIT_ON_TEARDOWN = True
FILES = ('php', 'png', 'jpg', 'jpeg', 'gif', 'jsp', 'asp')
PKL_DIR = os.getcwd() + "/pkl_objects/"

#%%
cv_pkl = PKL_DIR + "cv.pkl"
mlp_pkl = PKL_DIR + "mlp.pkl"
transformer_pkl = PKL_DIR + "transformer.pkl"

app = Flask(__name__)
app.config['UPLOADED_WEBSHELL_DSET'] = UPLOADED_WEBSHELL_DSET
app.config['SECRET_KEY'] = SECRET_KEY
app.config['SQLALCHEMY_DATABASE_URI'] = SQLALCHEMY_DATABASE_URI
app.config['SQLALCHEMY_COMMIT_ON_TEARDOWN'] = True
manager = Manager(app)
#%%

webshell = UploadSet('webshell', FILES)
webshell
app
configure_uploads(app, webshell)
patch_request_class(app)
#%%

class UploadForm(FlaskForm):
    '''
    This class define the upload form.
    You can only upload webshell in the form!
    '''
    files = FileField('webshell',validators=[
        FileRequired('You do not choose a webshell file'),
        FileAllowed(webshell, 'Webshell Only!')
    ])
    submit = SubmitField('Upload!')


#%%
db = SQLAlchemy(app)

class Webshell(db.Model):
    '''
    This class define the structure of the database.
    '''
    __tablename__ = 'webshell_info'
    id = db.column(db.Integer, primary_key=True)
    fname = db.column(db.String(32), unique=True)

    def __repr__(self):
        return '<Webshell %r>' % self.fname

#%%

@app.route('/upload', methods=['GET', 'POST'])
def upload():
    form = UploadForm()
    filename = list()
    if form.validate_on_submit():
        for f in request.files.getlist('webshells'):
            finame = hashlib.md5(('webshell_name' + str(time.time())).encode('UTF-8')).hexdigest()[:15]
            ffn = webshell.save(f, name=finame + '.')
            # webs = Webshell.query.filter_by(fname=finame).first()
            # if webs is None:
            #     webs = Webshell(fname = finame)
            #     db.session.add(webs)
            # if check(joblib.load(mlp_pkl), joblib.load(cv_pkl), joblib.load(transformer_pkl), UPLOADED_WEBSHELL_DSET, finame)
            filename.append(ffn)
        success = True
    else:
        success = False
    return render_template('upload.html', title='UPLOAD', form=form, success=success, filename=filename)


@app.route('/')
@app.route('/index')
def index():
    return render_template('index.html', title=Home)


@app.errorhandler(404)
def page_not_found(e):
    return render_template("404.html", title='Not Found'),404


@app.errorhandler(500)
def internal_server_error(e):
    return render_template('500.html', title="Internal Error"), 500

if __name__ == '__main__':
    app.run(debug=True)
